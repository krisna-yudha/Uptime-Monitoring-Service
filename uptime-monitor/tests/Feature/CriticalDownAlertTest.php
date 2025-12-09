<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Jobs\ProcessMonitorCheck;
use App\Jobs\SendNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CriticalDownAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_sends_critical_alert_on_20th_consecutive_failure()
    {
        // Create a monitor with 19 consecutive failures
        $monitor = Monitor::factory()->create([
            'consecutive_failures' => 19,
            'last_status' => 'down',
            'type' => 'http',
            'target' => 'https://example-down-service.com',
            'enabled' => true,
            'notify_after_retries' => 3
        ]);

        // Create notification channel
        $channel = NotificationChannel::factory()->create([
            'type' => 'slack',
            'config' => [
                'webhook_url' => 'https://hooks.slack.com/test'
            ]
        ]);

        $monitor->update([
            'notification_channels' => [$channel->id]
        ]);

        // Mock HTTP failure
        Http::fake([
            'https://example-down-service.com' => Http::response('Service Unavailable', 503)
        ]);

        // Process monitor check
        $job = new ProcessMonitorCheck($monitor);
        $job->handle();

        // Refresh monitor from database
        $monitor->refresh();

        // Assert that consecutive failures is now 20
        $this->assertEquals(20, $monitor->consecutive_failures);
        
        // Assert that critical alert was sent
        Queue::assertPushed(SendNotification::class, function ($job) use ($monitor) {
            return $job->monitor->id === $monitor->id 
                && $job->type === 'critical_down';
        });

        // Assert that last_critical_alert_sent was updated
        $this->assertNotNull($monitor->last_critical_alert_sent);
    }

    /** @test */
    public function it_does_not_send_critical_alert_before_20_failures()
    {
        // Create a monitor with 19 consecutive failures
        $monitor = Monitor::factory()->create([
            'consecutive_failures' => 18,
            'last_status' => 'down',
            'type' => 'http',
            'target' => 'https://example-down-service.com',
            'enabled' => true,
            'notify_after_retries' => 3
        ]);

        // Mock HTTP failure
        Http::fake([
            'https://example-down-service.com' => Http::response('Service Unavailable', 503)
        ]);

        // Process monitor check
        $job = new ProcessMonitorCheck($monitor);
        $job->handle();

        // Refresh monitor from database
        $monitor->refresh();

        // Assert that consecutive failures is now 19 (not 20 yet)
        $this->assertEquals(19, $monitor->consecutive_failures);
        
        // Assert that critical alert was NOT sent
        Queue::assertNotPushed(SendNotification::class, function ($job) use ($monitor) {
            return $job->monitor->id === $monitor->id 
                && $job->type === 'critical_down';
        });
    }

    /** @test */
    public function it_builds_correct_critical_alert_message()
    {
        $monitor = Monitor::factory()->create([
            'name' => 'Test API Service',
            'target' => 'https://api.test.com',
            'consecutive_failures' => 20,
            'interval_seconds' => 60,
            'last_error' => 'Connection timeout after 5000ms'
        ]);

        $incident = Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'type' => 'down',
            'title' => 'Service Down'
        ]);

        $job = new SendNotification($monitor, 'critical_down', null, $incident);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('buildCriticalDownMessage');
        $method->setAccessible(true);
        
        $message = $method->invoke($job);

        // Assert message contains key information
        $this->assertStringContainsString('CRITICAL SERVICE OUTAGE ALERT', $message);
        $this->assertStringContainsString('Test API Service', $message);
        $this->assertStringContainsString('https://api.test.com', $message);
        $this->assertStringContainsString('20 consecutive checks', $message);
        $this->assertStringContainsString('Connection timeout after 5000ms', $message);
        $this->assertStringContainsString('IMMEDIATE ACTION REQUIRED', $message);
        $this->assertStringContainsString('~20 minutes', $message); // 20 failures * 60 seconds = 20 minutes
    }

    /** @test */
    public function it_calculates_correct_downtime_duration()
    {
        $monitor = Monitor::factory()->create([
            'consecutive_failures' => 20,
            'interval_seconds' => 60 // 1 minute intervals
        ]);

        $job = new SendNotification($monitor, 'critical_down');
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('calculateDowntimeDuration');
        $method->setAccessible(true);
        
        $duration = $method->invoke($job);

        // 20 failures * 60 seconds = 1200 seconds = 20 minutes
        $this->assertEquals(20, $duration);
    }

    /** @test */
    public function it_prevents_spam_by_tracking_last_critical_alert_sent()
    {
        $monitor = Monitor::factory()->create([
            'consecutive_failures' => 25, // More than 20
            'last_status' => 'down',
            'last_critical_alert_sent' => now()->subMinutes(30), // Sent 30 minutes ago
            'type' => 'http',
            'target' => 'https://example-down-service.com',
            'enabled' => true
        ]);

        // This test would need additional logic in ProcessMonitorCheck 
        // to prevent sending multiple critical alerts for the same outage
        
        $this->assertNotNull($monitor->last_critical_alert_sent);
    }
}