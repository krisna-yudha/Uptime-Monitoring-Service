<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Incident;
use App\Models\MonitoringLog;
use App\Jobs\SendNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Throwable;

class ProcessMonitorCheck implements ShouldQueue
{
    use Queueable;

    protected Monitor $monitor;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes timeout

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(Monitor $monitor)
    {
        $this->monitor = $monitor;
        $this->onQueue('monitor-checks');
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // Log the failure
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            Log::warning("Monitor job failed - Monitor not found", [
                'exception' => $exception->getMessage(),
                'job_class' => static::class
            ]);
        } else {
            Log::error("Monitor job failed", [
                'exception' => $exception?->getMessage(),
                'job_class' => static::class
            ]);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if monitor still exists (in case it was deleted while job was queued)
        $monitor = Monitor::find($this->monitor->id);
        if (!$monitor) {
            Log::warning("Monitor job skipped - Monitor not found", [
                'monitor_id' => $this->monitor->id,
                'job_class' => static::class
            ]);
            return;
        }

        // Update our monitor reference to the fresh instance
        $this->monitor = $monitor;

        // Log check start
        MonitoringLog::logEvent(
            $this->monitor->id,
            'check_start',
            null,
            [
                'monitor_type' => $this->monitor->type,
                'monitor_url' => $this->monitor->target,
                'worker_pid' => getmypid(),
                'job_id' => $this->job?->getJobId() ?? 'manual'
            ]
        );

        // Debug entry for synchronous runs
        Log::info('ProcessMonitorCheck started', ['monitor_id' => $this->monitor->id, 'type' => $this->monitor->type]);

        // Try to acquire an advisory lock to ensure only one worker processes this monitor
        // Use PostgreSQL advisory lock when available, but gracefully fall back
        // for other DB engines (so synchronous immediate checks don't fail).
        $lockKey = $this->monitor->id;
        $lockAcquired = false;

        try {
            try {
                // Try to acquire advisory lock (Postgres)
                $lockResult = DB::select("SELECT pg_try_advisory_lock(?) as lock_acquired", [$lockKey]);
                $lockAcquired = $lockResult[0]->lock_acquired ?? false;
            } catch (\Exception $e) {
                // If DB does not support pg_try_advisory_lock (e.g., MySQL),
                // log and continue without advisory locking (assume single runner)
                Log::info('Advisory lock not supported or failed, continuing without lock: ' . $e->getMessage(), [
                    'monitor_id' => $this->monitor->id
                ]);
                $lockAcquired = true; // treat as acquired so release attempt runs safely
            }

            if ($lockAcquired === false) {
                MonitoringLog::logEvent(
                    $this->monitor->id,
                    'check_skipped',
                    null,
                    ['reason' => 'already_being_processed_by_another_worker']
                );
                Log::info("Monitor check skipped - already being processed by another worker", [
                    'monitor_id' => $this->monitor->id,
                    'monitor_name' => $this->monitor->name
                ]);
                return;
            }

            // Skip if monitor is disabled or paused
            if (!$this->monitor->enabled || $this->monitor->isPaused()) {
                return;
            }

            // First time check or unknown status - perform validation
            if ($this->monitor->last_status === null || $this->monitor->last_status === 'unknown') {
                $validationResult = $this->validateService();
                
                if (!$validationResult['valid']) {
                    $this->handleInvalidService($validationResult);
                    return;
                }
                
                // Service is valid, proceed with normal monitoring
                $this->monitor->update(['last_status' => 'validating']);
                
                MonitoringLog::logEvent(
                    $this->monitor->id,
                    'service_validated',
                    'validating',
                    [
                        'validation_result' => $validationResult,
                        'message' => 'Service validated successfully, starting monitoring'
                    ]
                );
            }

            $startTime = microtime(true);
            $status = 'unknown';
            $latency = null;
            $httpStatus = null;
            $errorMessage = null;
            $responseSize = null;
            $meta = [];

            try {
                switch ($this->monitor->type) {
                    case 'http':
                    case 'https':
                        $result = $this->checkHttp();
                        break;
                    case 'tcp':
                        $result = $this->checkTcp();
                        break;
                    case 'ping':
                        $result = $this->checkPing();
                        break;
                    case 'keyword':
                        $result = $this->checkKeyword();
                        break;
                    default:
                        throw new Exception("Unsupported monitor type: {$this->monitor->type}");
                }

                $status = $result['status'];
                $latency = $result['latency'];
                $httpStatus = $result['http_status'] ?? null;
                $responseSize = $result['response_size'] ?? null;
                $meta = $result['meta'] ?? [];

            } catch (Exception $e) {
                $status = 'down';
                $errorMessage = $e->getMessage();
                $latency = (microtime(true) - $startTime) * 1000;
                
                // Check if this is a critical error that should create incident immediately
                $isCriticalError = $this->isCriticalError($errorMessage);
                
                // Log check failure
                MonitoringLog::logEvent(
                    $this->monitor->id,
                    'check_failed',
                    'down',
                    [
                        'error' => $errorMessage,
                        'exception_type' => get_class($e),
                        'execution_time_ms' => $latency,
                        'is_critical_error' => $isCriticalError
                    ],
                    $latency,
                    $errorMessage
                );
            }

            // Create monitor check record
            try {
                Log::info('Creating MonitorCheck record', ['monitor_id' => $this->monitor->id, 'status' => $status]);
                $check = MonitorCheck::create([
                    'monitor_id' => $this->monitor->id,
                    'checked_at' => now(),
                    'status' => $status,
                    'latency_ms' => $latency ? round($latency) : null,
                    'http_status' => $httpStatus,
                    'error_message' => $errorMessage,
                    'response_size' => $responseSize,
                    'region' => 'local', // Can be expanded for multiple regions
                    'meta' => $meta,
                ]);
                Log::info('MonitorCheck record created', ['monitor_id' => $this->monitor->id, 'check_id' => $check->id ?? null]);
            } catch (\Exception $e) {
                Log::error('Failed to create MonitorCheck record', ['monitor_id' => $this->monitor->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                // Continue so monitor status updates still happen
                $check = null;
            }

            // Log successful check completion
            MonitoringLog::logEvent(
                $this->monitor->id,
                'check_complete',
                $status,
                [
                    'check_id' => $check->id,
                    'http_status' => $httpStatus,
                    'response_size' => $responseSize,
                    'meta' => $meta,
                    'execution_time_ms' => $latency
                ],
                $latency,
                $errorMessage
            );

            // Update monitor status and consecutive failures
            $previousStatus = $this->monitor->last_status;
            $consecutiveFailures = $status === 'down' ? 
                ($previousStatus === 'down' ? $this->monitor->consecutive_failures + 1 : 1) : 0;

            $this->monitor->update([
                'last_status' => $status,
                'last_checked_at' => now(),
                'next_check_at' => now()->addSeconds($this->monitor->interval_seconds),
                'consecutive_failures' => $consecutiveFailures,
                'error_message' => $status === 'down' ? $errorMessage : null,
                'last_error_at' => $status === 'down' ? now() : null,
            ]);

            // Log status change if detected
            if ($previousStatus !== $status) {
                MonitoringLog::logEvent(
                    $this->monitor->id,
                    'status_change',
                    $status,
                    [
                        'previous_status' => $previousStatus,
                        'new_status' => $status,
                        'consecutive_failures' => $consecutiveFailures,
                        'check_id' => $check->id
                    ]
                );
            }

            // Handle incident management (FR-12, FR-13)
            $this->handleIncidents($status, $previousStatus, $errorMessage, $httpStatus);

            // Log the check
            Log::info("Monitor check completed", [
                'monitor_id' => $this->monitor->id,
                'monitor_name' => $this->monitor->name,
                'status' => $status,
                'latency' => $latency,
                'status_changed' => $previousStatus !== $status,
            ]);

            // Auto-requeue for next check (self-perpetuating monitoring)
            // This ensures monitoring continues even if scheduler is not running
            try {
                $delay = $this->monitor->interval_seconds;
                
                // Refresh monitor to get latest state
                $freshMonitor = Monitor::find($this->monitor->id);
                
                // Only requeue if monitor still exists, is enabled, and not paused
                if ($freshMonitor && $freshMonitor->enabled) {
                    $isPaused = $freshMonitor->pause_until && $freshMonitor->pause_until > now();
                    
                    if (!$isPaused && $freshMonitor->type !== 'push') {
                        // Use delay to schedule next check based on interval
                        ProcessMonitorCheck::dispatch($freshMonitor)
                            ->delay(now()->addSeconds($delay));
                        
                        Log::info("Monitor auto-requeued for next check", [
                            'monitor_id' => $freshMonitor->id,
                            'delay_seconds' => $delay,
                            'next_check_at' => now()->addSeconds($delay)->toDateTimeString()
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to auto-requeue monitor", [
                    'monitor_id' => $this->monitor->id,
                    'error' => $e->getMessage()
                ]);
            }

        } finally {
            // Always release the advisory lock
            if ($lockAcquired) {
                try {
                    // Try to release Postgres advisory lock if supported
                    DB::select("SELECT pg_advisory_unlock(?)", [$lockKey]);
                } catch (\Exception $e) {
                    // If DB does not support advisory unlock, just ignore
                    Log::debug('Advisory unlock not supported or failed: ' . $e->getMessage(), [
                        'monitor_id' => $this->monitor->id
                    ]);
                }
            }
        }
    }

    protected function checkHttp(): array
    {
        $startTime = microtime(true);
        $config = $this->monitor->config ?? [];
        
        // Set both timeout and connectTimeout to prevent hanging connections
        // connectTimeout is crucial for connections to different ports (e.g., 192.168.88.241:8080)
        $timeoutSeconds = $this->monitor->timeout_ms / 1000;
        $connectTimeoutSeconds = min($timeoutSeconds, 10); // Max 10 seconds for connection
        
        $httpClient = Http::timeout($timeoutSeconds)
            ->connectTimeout($connectTimeoutSeconds)
            ->retry($this->monitor->retries, 1000)
            ->withOptions(['allow_redirects' => true]);

        // Add custom headers if configured
        if (isset($config['headers'])) {
            $httpClient = $httpClient->withHeaders($config['headers']);
        }

        // Add basic auth if configured
        if (isset($config['auth']) && $config['auth']['type'] === 'basic') {
            $httpClient = $httpClient->withBasicAuth(
                $config['auth']['username'],
                $config['auth']['password']
            );
        }

        // Handle SSL verification setting - default to not verify for flexibility
        $verifySSL = isset($config['verify_ssl']) ? $config['verify_ssl'] : false;
        if (!$verifySSL) {
            $httpClient = $httpClient->withoutVerifying();
        }

        $response = $httpClient->get($this->monitor->target);
        $latency = (microtime(true) - $startTime) * 1000;

        $status = 'up';
        $meta = [
            'response_headers' => array_slice($response->headers(), 0, 10), // Limit headers
        ];

        // Check expected status code
        if (isset($config['expected_status_code'])) {
            if ($response->status() !== $config['expected_status_code']) {
                $status = 'down';
            }
        } else {
            // Default: consider 2xx and 3xx as up
            if ($response->status() >= 400) {
                $status = 'down';
            }
        }

        // Check for expected content
        if (isset($config['expected_content']) && $status === 'up') {
            $body = $response->body();
            if (!str_contains($body, $config['expected_content'])) {
                $status = 'down';
            }
            $meta['body_snippet'] = substr($body, 0, 500);
        }

        // Check SSL certificate expiry for HTTPS
        if ($this->monitor->type === 'https') {
            $meta['ssl_expiry'] = $this->getSSLExpiryDate($this->monitor->target);
        }

        return [
            'status' => $status,
            'latency' => $latency,
            'http_status' => $response->status(),
            'response_size' => strlen($response->body()),
            'meta' => $meta,
        ];
    }

    protected function checkTcp(): array
    {
        $startTime = microtime(true);
        $config = $this->monitor->config ?? [];
        
        $target = $this->monitor->target;
        $parts = explode(':', $target);
        $host = $parts[0];
        $port = isset($parts[1]) ? (int) $parts[1] : 80;

        $socket = @fsockopen($host, $port, $errno, $errstr, $this->monitor->timeout_ms / 1000);
        $latency = (microtime(true) - $startTime) * 1000;

        if ($socket) {
            fclose($socket);
            $status = 'up';
        } else {
            $status = 'down';
            throw new Exception("TCP connection failed: $errstr ($errno)");
        }

        return [
            'status' => $status,
            'latency' => $latency,
            'meta' => [
                'host' => $host,
                'port' => $port,
            ],
        ];
    }

    protected function checkPing(): array
    {
        $startTime = microtime(true);
        $host = $this->monitor->target;
        
        // Use system ping command
        $output = [];
        $returnCode = 0;
        
        if (PHP_OS_FAMILY === 'Windows') {
            exec("ping -n 1 -w " . $this->monitor->timeout_ms . " $host", $output, $returnCode);
        } else {
            exec("ping -c 1 -W " . ($this->monitor->timeout_ms / 1000) . " $host", $output, $returnCode);
        }
        
        $latency = (microtime(true) - $startTime) * 1000;
        $status = ($returnCode === 0) ? 'up' : 'down';

        if ($status === 'down') {
            throw new Exception("Ping failed for host: $host");
        }

        // Extract latency from ping output if available
        $pingLatency = $this->extractPingLatency(implode("\n", $output));

        return [
            'status' => $status,
            'latency' => $pingLatency ?? $latency,
            'meta' => [
                'host' => $host,
                'ping_output' => implode("\n", array_slice($output, 0, 3)),
            ],
        ];
    }

    protected function checkKeyword(): array
    {
        $config = $this->monitor->config ?? [];
        $keyword = $config['keyword'] ?? '';

        if (empty($keyword)) {
            throw new Exception("Keyword not configured for monitor");
        }

        // First perform HTTP check
        $httpResult = $this->checkHttp();
        
        // Then check for keyword
        if ($httpResult['status'] === 'up') {
            $timeoutSeconds = $this->monitor->timeout_ms / 1000;
            $connectTimeoutSeconds = min($timeoutSeconds, 10);
            
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout($connectTimeoutSeconds)
                ->get($this->monitor->target);
                
            $body = $response->body();
            
            if (!str_contains($body, $keyword)) {
                $httpResult['status'] = 'down';
                $httpResult['meta']['keyword_found'] = false;
            } else {
                $httpResult['meta']['keyword_found'] = true;
            }
        }

        return $httpResult;
    }

    protected function handleIncidents(string $currentStatus, string $previousStatus, ?string $errorMessage = null, ?int $httpStatus = null): void
    {
        $lastIncident = $this->monitor->incidents()
            ->where('resolved', false)
            ->latest('started_at')
            ->first();

        // Check if this is a critical error that requires immediate incident creation
        $isCriticalError = $errorMessage && $this->isCriticalError($errorMessage, $httpStatus);

        // Create incident immediately for critical errors (internal server error, unreachable, connection failed)
        if ($currentStatus === 'down' && $isCriticalError && !$lastIncident) {
            $incidentDescription = $this->getCriticalErrorDescription($errorMessage, $httpStatus);
            
            $incident = Incident::create([
                'monitor_id' => $this->monitor->id,
                'started_at' => now(),
                'resolved' => false,
                'status' => 'open',
                'alert_status' => 'none',
                'description' => $incidentDescription,
            ]);

            Log::warning("Critical error incident created immediately", [
                'monitor_id' => $this->monitor->id,
                'incident_id' => $incident->id,
                'error_message' => $errorMessage,
                'http_status' => $httpStatus,
                'error_type' => 'critical'
            ]);

            // Send notification immediately for critical errors
            SendNotification::dispatch($this->monitor, 'down', $incident);
            return;
        }

        // Normal status change handling
        if ($currentStatus === $previousStatus && !$isCriticalError) {
            return;
        }

        if ($currentStatus === 'down' && $previousStatus !== 'down') {
            // Start new incident when going from UP/UNKNOWN to DOWN (FR-12)
            if (!$lastIncident) {
                $incidentDescription = $errorMessage 
                    ? "Monitor went down: {$errorMessage}" 
                    : "Monitor went down from status: {$previousStatus}";

                $incident = Incident::create([
                    'monitor_id' => $this->monitor->id,
                    'started_at' => now(),
                    'resolved' => false,
                    'status' => 'open',
                    'alert_status' => 'none',
                    'description' => $incidentDescription,
                ]);

                Log::info("New incident created", [
                    'monitor_id' => $this->monitor->id,
                    'incident_id' => $incident->id,
                    'previous_status' => $previousStatus,
                    'current_status' => $currentStatus,
                ]);

                // Send notification only if consecutive failures meet threshold (FR-16: Anti-spam)
                // Require at least 10 consecutive failures before triggering notifications to bots.
                $notifyThreshold = isset($this->monitor->notify_after_retries) ? (int) $this->monitor->notify_after_retries : 10;
                $effectiveThreshold = max(10, $notifyThreshold);
                if ($this->monitor->consecutive_failures >= $effectiveThreshold) {
                    SendNotification::dispatch($this->monitor, 'down', $incident);
                }

                // Critical Alert: Send special notification when service is down 20 times consecutively
                // Only send if we haven't already sent a critical alert for this outage
                if ($this->monitor->consecutive_failures == 20 && !$this->hasCriticalAlertBeenSent()) {
                    $this->sendCriticalDownAlert($incident);
                    
                    // Set incident status based on alert handling
                    if ($incident && !$incident->hasCriticalAlertBeenSent()) {
                        $incident->updateAlertStatus('critical_sent', [
                            'consecutive_failures' => $this->monitor->consecutive_failures,
                            'estimated_downtime_minutes' => $this->calculateDowntimeDuration()
                        ]);
                        
                        // Mark as pending if not yet handled
                        if ($incident->status === 'open') {
                            $incident->update(['status' => 'pending']);
                            $incident->logAlert('incident_escalated', 'Incident escalated to pending due to critical alert');
                        }
                    }
                }
            }
        } else if ($currentStatus === 'up' && $previousStatus === 'down' && $lastIncident) {
            // Resolve existing incident when going from DOWN to UP (FR-13)
            $duration = now()->diffInSeconds($lastIncident->started_at);
            
            // Auto-resolve incident when service comes back up
            $lastIncident->update([
                'ended_at' => now(),
                'resolved' => true,
                'status' => 'resolved',
                'description' => ($lastIncident->description ?? '') . " | Resolved after {$duration} seconds",
            ]);

            // Log the resolution
            $lastIncident->logAlert('incident_auto_resolved', 'Incident automatically resolved - service back online', [
                'duration_seconds' => $duration,
                'resolution_method' => 'automatic',
                'final_status' => 'up'
            ]);

            Log::info("Incident resolved", [
                'monitor_id' => $this->monitor->id,
                'incident_id' => $lastIncident->id,
                'duration_seconds' => $duration,
                'previous_status' => $previousStatus,
                'current_status' => $currentStatus,
                'final_incident_status' => $lastIncident->status,
            ]);

            // Send recovery notification (FR-14)
            SendNotification::dispatch($this->monitor, 'up', $lastIncident);
        }
    }

    protected function getSSLExpiryDate(string $url): ?string
    {
        try {
            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'];
            $port = $parsedUrl['port'] ?? 443;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'capture_peer_cert' => true,
                ]
            ]);

            $socket = @stream_socket_client(
                "ssl://$host:$port",
                $errno,
                $errstr,
                $this->monitor->timeout_ms / 1000,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($socket) {
                $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
                $certInfo = openssl_x509_parse($cert);
                fclose($socket);

                $expiryDate = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
                $issuer = $certInfo['issuer']['O'] ?? ($certInfo['issuer']['CN'] ?? 'Unknown');

                // Update monitor SSL info
                $this->monitor->update([
                    'ssl_cert_expiry' => $expiryDate,
                    'ssl_cert_issuer' => $issuer,
                    'ssl_checked_at' => now()
                ]);

                return $expiryDate;
            }
        } catch (Exception $e) {
            Log::warning("Failed to get SSL expiry date for $url: " . $e->getMessage());
        }

        return null;
    }

    protected function extractPingLatency(string $pingOutput): ?float
    {
        // Extract latency from ping output (works for both Windows and Linux)
        if (preg_match('/time[=<](\d+(?:\.\d+)?)ms/i', $pingOutput, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Validate if the service/target is valid and reachable
     */
    private function validateService(): array
    {
        MonitoringLog::logEvent(
            $this->monitor->id,
            'validation_start',
            'unknown',
            [
                'target' => $this->monitor->target,
                'type' => $this->monitor->type
            ]
        );

        try {
            switch ($this->monitor->type) {
                case 'http':
                case 'https':
                    return $this->validateHttpService();
                case 'tcp':
                    return $this->validateTcpService();
                case 'ping':
                    return $this->validatePingService();
                case 'keyword':
                    return $this->validateKeywordService();
                default:
                    return [
                        'valid' => false,
                        'reason' => 'Unsupported monitor type: ' . $this->monitor->type,
                        'error_code' => 'UNSUPPORTED_TYPE'
                    ];
            }
        } catch (Exception $e) {
            return [
                'valid' => false,
                'reason' => 'Validation failed: ' . $e->getMessage(),
                'error_code' => 'VALIDATION_EXCEPTION',
                'exception' => get_class($e)
            ];
        }
    }

    /**
     * Validate HTTP/HTTPS service
     */
    private function validateHttpService(): array
    {
        $url = $this->monitor->target;
        
        // Check if URL is properly formatted
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'reason' => 'Invalid URL format: ' . $url,
                'error_code' => 'INVALID_URL'
            ];
        }

        // Parse URL to check components
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            return [
                'valid' => false,
                'reason' => 'URL must use HTTP or HTTPS scheme',
                'error_code' => 'INVALID_SCHEME'
            ];
        }

        if (!isset($parsed['host']) || empty($parsed['host'])) {
            return [
                'valid' => false,
                'reason' => 'URL must contain a valid host',
                'error_code' => 'INVALID_HOST'
            ];
        }

        // Check if host is resolvable
        $ip = gethostbyname($parsed['host']);
        if ($ip === $parsed['host'] && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'valid' => false,
                'reason' => 'Host is not resolvable: ' . $parsed['host'],
                'error_code' => 'HOST_NOT_RESOLVABLE'
            ];
        }

        // Perform basic HTTP validation request
        try {
            $config = $this->monitor->config ?? [];
            $timeout = $config['timeout'] ?? 30;
            $verifySSL = $config['verify_ssl'] ?? false;
            
            $httpClient = Http::timeout($timeout)
                ->retry(1, 1000)
                ->connectTimeout(10)
                ->withOptions(['allow_redirects' => true]);
                
            if (!$verifySSL) {
                $httpClient = $httpClient->withoutVerifying();
            }
            
            // Use HEAD request for validation to minimize bandwidth
            $response = $httpClient->head($url);
            
            // Accept any HTTP response (even errors) as valid - the service exists
            return [
                'valid' => true,
                'reason' => 'Service responds to HTTP requests',
                'http_status' => $response->status(),
                'resolved_ip' => $ip,
                'validation_method' => 'HEAD_REQUEST'
            ];
            
        } catch (Exception $e) {
            // If HEAD fails, try a simple GET with minimal timeout
            try {
                $response = $httpClient->timeout(5)->get($url);
                return [
                    'valid' => true,
                    'reason' => 'Service responds to HTTP requests (fallback GET)',
                    'http_status' => $response->status(),
                    'resolved_ip' => $ip,
                    'validation_method' => 'GET_FALLBACK'
                ];
            } catch (Exception $fallbackError) {
                return [
                    'valid' => false,
                    'reason' => 'Service is not reachable: ' . $e->getMessage(),
                    'error_code' => 'SERVICE_UNREACHABLE',
                    'resolved_ip' => $ip,
                    'original_error' => $e->getMessage(),
                    'fallback_error' => $fallbackError->getMessage()
                ];
            }
        }
    }

    /**
     * Validate TCP service
     */
    private function validateTcpService(): array
    {
        $target = $this->monitor->target;
        $config = $this->monitor->config ?? [];
        $timeout = $config['timeout'] ?? 30;
        
        // Parse target (host:port)
        if (!str_contains($target, ':')) {
            return [
                'valid' => false,
                'reason' => 'TCP target must include port (host:port format)',
                'error_code' => 'INVALID_TCP_FORMAT'
            ];
        }
        
        [$host, $port] = explode(':', $target, 2);
        
        if (!is_numeric($port) || $port < 1 || $port > 65535) {
            return [
                'valid' => false,
                'reason' => 'Invalid port number: ' . $port,
                'error_code' => 'INVALID_PORT'
            ];
        }
        
        // Check if host is resolvable
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'valid' => false,
                'reason' => 'Host is not resolvable: ' . $host,
                'error_code' => 'HOST_NOT_RESOLVABLE'
            ];
        }
        
        // Test TCP connection
        $socket = @fsockopen($host, (int)$port, $errno, $errstr, min($timeout, 10));
        
        if ($socket) {
            fclose($socket);
            return [
                'valid' => true,
                'reason' => 'TCP service is reachable',
                'resolved_ip' => $ip,
                'port' => $port
            ];
        } else {
            return [
                'valid' => false,
                'reason' => "TCP connection failed: $errstr ($errno)",
                'error_code' => 'TCP_CONNECTION_FAILED',
                'resolved_ip' => $ip,
                'port' => $port,
                'errno' => $errno,
                'errstr' => $errstr
            ];
        }
    }

    /**
     * Validate PING service
     */
    private function validatePingService(): array
    {
        $host = $this->monitor->target;
        
        // Check if host is resolvable
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'valid' => false,
                'reason' => 'Host is not resolvable: ' . $host,
                'error_code' => 'HOST_NOT_RESOLVABLE'
            ];
        }
        
        // Perform basic ping validation
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "ping -n 1 -w 5000 " . escapeshellarg($host);
        } else {
            $cmd = "ping -c 1 -W 5 " . escapeshellarg($host);
        }
        
        $output = [];
        $returnVar = 0;
        exec($cmd . " 2>&1", $output, $returnVar);
        
        if ($returnVar === 0) {
            return [
                'valid' => true,
                'reason' => 'Host responds to ping',
                'resolved_ip' => $ip,
                'ping_output' => implode("\n", array_slice($output, 0, 3)) // First 3 lines only
            ];
        } else {
            return [
                'valid' => false,
                'reason' => 'Host does not respond to ping',
                'error_code' => 'PING_FAILED',
                'resolved_ip' => $ip,
                'ping_output' => implode("\n", $output),
                'return_code' => $returnVar
            ];
        }
    }

    /**
     * Validate keyword monitoring service
     */
    private function validateKeywordService(): array
    {
        // For keyword monitoring, we need to validate the underlying HTTP service first
        $httpValidation = $this->validateHttpService();
        
        if (!$httpValidation['valid']) {
            return $httpValidation; // Return the HTTP validation result
        }
        
        $config = $this->monitor->config ?? [];
        $keyword = $config['keyword'] ?? '';
        
        if (empty($keyword)) {
            return [
                'valid' => false,
                'reason' => 'Keyword is not configured for keyword monitoring',
                'error_code' => 'MISSING_KEYWORD'
            ];
        }
        
        return [
            'valid' => true,
            'reason' => 'HTTP service is reachable and keyword is configured',
            'keyword' => $keyword,
            'http_validation' => $httpValidation
        ];
    }

    /**
     * Handle invalid service by updating monitor status and creating incident
     */
    private function handleInvalidService(array $validationResult): void
    {
        // Update monitor status to invalid
        $this->monitor->update([
            'last_status' => 'invalid',
            'last_checked_at' => now(),
            'last_error' => $validationResult['reason'],
            'error_message' => $validationResult['reason'],
            'last_error_at' => now(),
            // Schedule next check so monitoring will retry automatically
            'next_check_at' => now()->addSeconds($this->monitor->interval_seconds ?? 60),
        ]);
        
        // Create a monitor check record
        MonitorCheck::create([
            'monitor_id' => $this->monitor->id,
            'checked_at' => now(),
            'status' => 'invalid',
            'latency_ms' => null,
            'http_status' => null,
            'error_message' => $validationResult['reason'],
            'response_size' => null,
            'region' => 'local',
            'meta' => json_encode($validationResult)
        ]);
        
        // Log the validation failure
        MonitoringLog::logEvent(
            $this->monitor->id,
            'validation_failed',
            'invalid',
            array_merge($validationResult, [
                'message' => 'Service validation failed, monitoring disabled until fixed'
            ])
        );
        
        // Create or update incident for invalid service
        $existingIncident = Incident::where('monitor_id', $this->monitor->id)
            ->where('status', 'open')
            ->where('type', 'validation_failed')
            ->first();
            
        if (!$existingIncident) {
            $incident = Incident::create([
                'monitor_id' => $this->monitor->id,
                'type' => 'validation_failed',
                'status' => 'open',
                'started_at' => now(),
                'title' => 'Service Validation Failed: ' . $this->monitor->name,
                'description' => $validationResult['reason'],
                'severity' => 'high',
                'meta' => json_encode($validationResult)
            ]);
            
            // Send notification about invalid service
            if ($this->monitor->notification_enabled) {
                SendNotification::dispatch(
                    $this->monitor,
                    'validation_failed',
                    "Service validation failed for {$this->monitor->name}: {$validationResult['reason']}",
                    $incident
                );
            }
        }
    }

    /**
     * Send critical alert when service has been down for 20 consecutive checks
     * This indicates a serious service outage that requires immediate attention
     */
    private function sendCriticalDownAlert($incident): void
    {
        try {
            // Log the critical alert
            Log::critical("CRITICAL ALERT: Service down for 20 consecutive checks", [
                'monitor_id' => $this->monitor->id,
                'monitor_name' => $this->monitor->name,
                'monitor_target' => $this->monitor->target,
                'consecutive_failures' => $this->monitor->consecutive_failures,
                'incident_id' => $incident?->id,
                'last_error' => $this->monitor->last_error,
                'started_failing_at' => $this->monitor->last_error_at,
                'notification_timestamp' => now()->toISOString()
            ]);

            // Create monitoring log entry for critical alert
            MonitoringLog::logEvent(
                $this->monitor->id,
                'critical_down_alert',
                'down',
                [
                    'consecutive_failures' => $this->monitor->consecutive_failures,
                    'alert_type' => 'critical_service_outage',
                    'requires_immediate_attention' => true,
                    'incident_id' => $incident?->id,
                    'downtime_duration_minutes' => $this->calculateDowntimeDuration(),
                ]
            );

            // Send critical notification to all notification channels
            // Use high priority and special message format
            $criticalMessage = $this->buildCriticalAlertMessage();
            
            SendNotification::dispatch(
                $this->monitor, 
                'critical_down',
                $criticalMessage,
                $incident,
                [
                    'priority' => 'critical',
                    'alert_type' => 'service_outage_20_failures',
                    'requires_immediate_action' => true
                ]
            );

            // Update monitor with critical alert flag to prevent spam
            $this->monitor->update([
                'last_critical_alert_sent' => now()
            ]);

        } catch (Exception $e) {
            Log::error("Failed to send critical down alert", [
                'monitor_id' => $this->monitor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Build critical alert message with detailed information
     */
    private function buildCriticalAlertMessage(): string
    {
        $downtime = $this->calculateDowntimeDuration();
        
        return "🚨 CRITICAL SERVICE OUTAGE ALERT 🚨\n\n" .
               "Service: {$this->monitor->name}\n" .
               "Target: {$this->monitor->target}\n" .
               "Status: DOWN for {$this->monitor->consecutive_failures} consecutive checks\n" .
               "Estimated Downtime: ~{$downtime} minutes\n" .
               "Last Error: {$this->monitor->last_error}\n\n" .
               "⚠️ IMMEDIATE ACTION REQUIRED ⚠️\n" .
               "This service has been unresponsive for an extended period.\n" .
               "Please investigate and resolve this issue immediately.\n\n" .
               "Incident Time: " . now()->format('Y-m-d H:i:s T') . "\n" .
               "Alert Generated: " . now()->toISOString();
    }

    /**
     * Check if error message indicates a critical error
     */
    protected function isCriticalError(?string $errorMessage, ?int $httpStatus = null): bool
    {
        if (!$errorMessage && !$httpStatus) {
            return false;
        }

        // Check HTTP status codes (500-599 are server errors)
        if ($httpStatus >= 500 && $httpStatus < 600) {
            return true;
        }

        // Check error message patterns
        $criticalPatterns = [
            '/internal server error/i',
            '/500 internal/i',
            '/502 bad gateway/i',
            '/503 service unavailable/i',
            '/504 gateway timeout/i',
            '/host unreachable/i',
            '/connection refused/i',
            '/connection timed out/i',
            '/connection failed/i',
            '/could not resolve host/i',
            '/network unreachable/i',
            '/no route to host/i',
            '/tcp connection failed/i',
            '/ping failed/i',
            '/service unavailable/i',
            '/cannot connect/i',
            '/failed to connect/i',
        ];

        foreach ($criticalPatterns as $pattern) {
            if (preg_match($pattern, $errorMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get human-readable description for critical errors
     */
    protected function getCriticalErrorDescription(?string $errorMessage, ?int $httpStatus = null): string
    {
        $description = "🚨 Critical Error Detected: ";

        // Specific HTTP status code descriptions
        if ($httpStatus) {
            switch ($httpStatus) {
                case 500:
                    $description .= "Internal Server Error (500) - The server encountered an unexpected condition";
                    break;
                case 502:
                    $description .= "Bad Gateway (502) - Invalid response from upstream server";
                    break;
                case 503:
                    $description .= "Service Unavailable (503) - Service is temporarily unavailable";
                    break;
                case 504:
                    $description .= "Gateway Timeout (504) - Upstream server timeout";
                    break;
                default:
                    if ($httpStatus >= 500) {
                        $description .= "Server Error ({$httpStatus})";
                    }
            }
        }

        // Check for specific error patterns
        if ($errorMessage) {
            if (stripos($errorMessage, 'host unreachable') !== false || 
                stripos($errorMessage, 'network unreachable') !== false) {
                $description .= " - Host/Network Unreachable - Cannot reach the target server";
            } elseif (stripos($errorMessage, 'connection refused') !== false) {
                $description .= " - Connection Refused - Server actively refused the connection";
            } elseif (stripos($errorMessage, 'connection timed out') !== false || 
                      stripos($errorMessage, 'connection failed') !== false) {
                $description .= " - Connection Failed - Unable to establish connection with server";
            } elseif (stripos($errorMessage, 'could not resolve host') !== false) {
                $description .= " - DNS Resolution Failed - Cannot resolve hostname";
            } elseif (!$httpStatus) {
                $description .= " - " . $errorMessage;
            }
        }

        $description .= " | Incident created at " . now()->format('Y-m-d H:i:s');
        
        return $description;
    }

    /**
     * Check if a critical alert has already been sent for the current outage
     * A critical alert is considered "already sent" if:
     * 1. last_critical_alert_sent is not null, AND
     * 2. The alert was sent after the current outage started (after last successful check)
     */
    private function hasCriticalAlertBeenSent(): bool
    {
        if (!$this->monitor->last_critical_alert_sent) {
            return false;
        }

        // Find the most recent successful check before the current outage
        $lastSuccessfulCheck = MonitorCheck::where('monitor_id', $this->monitor->id)
            ->where('status', 'up')
            ->orderBy('checked_at', 'desc')
            ->first();

        if (!$lastSuccessfulCheck) {
            // If no successful check found, check if alert was sent recently (within last hour)
            return $this->monitor->last_critical_alert_sent->greaterThan(now()->subHour());
        }

        // Critical alert was sent after the last successful check, so it's for this outage
        return $this->monitor->last_critical_alert_sent->greaterThan($lastSuccessfulCheck->checked_at);
    }

    /**
     * Calculate approximate downtime duration in minutes
     */
    private function calculateDowntimeDuration(): int
    {
        // Estimate downtime based on consecutive failures and check interval
        $estimatedDowntimeSeconds = $this->monitor->consecutive_failures * $this->monitor->interval_seconds;
        return (int) ceil($estimatedDowntimeSeconds / 60);
    }
}
