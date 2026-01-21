# Backend Flowchart

## 0. Complete End-to-End Flow (Frontend to Backend to Database)

```mermaid
flowchart TD
    Start([Start: User opens app]) --> FrontendLoad[Frontend: Load dashboard]
    FrontendLoad --> DisplayUI[Display Vue.js UI]
    
    subgraph Frontend["FRONTEND (Vue.js)"]
        DisplayUI --> UserAction{User Action?}
        UserAction -->|Create Monitor| OpenForm[Open monitor form]
        UserAction -->|View Dashboard| FetchData[Request monitor list]
        UserAction -->|View Details| FetchDetails[Request monitor details]
        
        OpenForm --> FillForm[User fills monitor info:<br/>Name, URL, Type, Interval]
        FillForm --> ValidateForm{Form valid?}
        ValidateForm -->|No| ShowError[Show validation error]
        ShowError --> FillForm
        ValidateForm -->|Yes| SendCreate[/Send POST /api/monitors<br/>with JWT token/]
    end
    
    subgraph Backend["BACKEND (Laravel API)"]
        SendCreate --> APIReceive[Receive API request]
        FetchData --> APIReceive
        FetchDetails --> APIReceive
        
        APIReceive --> AuthCheck{Valid JWT?}
        AuthCheck -->|No| Return401[/Return 401 Unauthorized/]
        AuthCheck -->|Yes| RouteCheck{Which endpoint?}
        
        RouteCheck -->|POST /monitors| ValidateInput[Validate monitor data]
        RouteCheck -->|GET /monitors| QueryMonitors[(Query monitors table)]
        RouteCheck -->|GET /monitors/:id| QueryDetails[(Query monitor + logs)]
        
        ValidateInput --> InputValid{Valid?}
        InputValid -->|No| Return422[/Return 422 Validation Error/]
        InputValid -->|Yes| SaveMonitor[(INSERT INTO monitors table)]
        
        SaveMonitor --> SetNextCheck[Calculate next_check_at time]
        SetNextCheck --> ReturnSuccess[/Return 201 Monitor created/]
        
        QueryMonitors --> ReturnMonitors[/Return 200 with monitors/]
        QueryDetails --> ReturnDetails[/Return 200 with details/]
    end
    
    subgraph Database["DATABASE (PostgreSQL)"]
        SaveMonitor --> DBMonitors[(monitors table:<br/>id, name, url, type,<br/>interval_seconds,<br/>next_check_at, status)]
        QueryMonitors --> DBMonitors
        QueryDetails --> DBMonitors
        QueryDetails --> DBLogs[(monitoring_logs table)]
    end
    
    ReturnSuccess --> FrontendReceive[Frontend receives response]
    Return422 --> FrontendReceive
    Return401 --> FrontendReceive
    ReturnMonitors --> FrontendReceive
    ReturnDetails --> FrontendReceive
    
    FrontendReceive --> UpdateUI[Update Vue.js UI]
    UpdateUI --> ShowSuccess{Success?}
    ShowSuccess -->|Yes| RefreshList[Refresh monitor list]
    ShowSuccess -->|No| ShowAPIError[Show error message]
    
    RefreshList --> WaitScheduler[Monitor saved, waiting for scheduler]
    
    subgraph Scheduler["SCHEDULER (Laravel Command)"]
        WaitScheduler --> SchedulerRun([Scheduler runs every second])
        SchedulerRun --> CheckDue[(Query monitors WHERE<br/>next_check_at <= NOW)]
        CheckDue --> MonitorFound{Monitors found?}
        MonitorFound -->|Yes| RunCheck[Execute monitor check]
        MonitorFound -->|No| WaitNext[Wait next second]
        WaitNext --> SchedulerRun
        
        RunCheck --> CheckMonitorType{Monitor type?}
        CheckMonitorType -->|HTTP| HTTPRequest[Send HTTP GET request]
        CheckMonitorType -->|Port| PortConnect[Try TCP connection]
        CheckMonitorType -->|SSL| SSLCheck[Check SSL certificate]
        
        HTTPRequest --> EvalResponse{Status 200<br/>& timeout OK?}
        PortConnect --> EvalPort{Port open?}
        SSLCheck --> EvalSSL{SSL valid<br/>& not expired?}
        
        EvalResponse -->|Yes| StatusUp[Status: UP]
        EvalResponse -->|No| StatusDown[Status: DOWN]
        EvalPort -->|Yes| StatusUp
        EvalPort -->|No| StatusDown
        EvalSSL -->|Yes| StatusUp
        EvalSSL -->|No| StatusDown
        
        StatusUp --> SaveLog[(INSERT INTO monitoring_logs)]
        StatusDown --> SaveLog
        
        SaveLog --> UpdateMonitor[(UPDATE monitors SET<br/>status, last_check_at,<br/>next_check_at)]
        UpdateMonitor --> CheckStatusChange{Status changed?}
        
        CheckStatusChange -->|Yes| TriggerIncident[(INSERT/UPDATE incidents)]
        CheckStatusChange -->|No| UpdateMetrics[Update metrics]
        
        TriggerIncident --> QueueNotification[(INSERT INTO jobs queue)]
        QueueNotification --> UpdateMetrics
        UpdateMetrics --> SchedulerRun
    end
    
    subgraph QueueWorker["QUEUE WORKER"]
        QueueNotification --> WorkerPick[Worker picks job from Redis]
        WorkerPick --> ProcessNotif[Process notification]
        ProcessNotif --> SendChannels[Send to Email/Telegram/Slack]
        SendChannels --> LogNotification[(INSERT INTO notification_logs)]
        LogNotification --> DeleteJob[Delete job from queue]
    end
    
    subgraph FrontendPolling["FRONTEND (Real-time Updates)"]
        DeleteJob --> PollInterval[Frontend polls every 10s]
        UpdateMetrics --> PollInterval
        PollInterval --> FetchLatest[/GET /api/monitors/]
        FetchLatest --> APIReceive
    end
    
    ShowAPIError --> End([End])
    RefreshList --> End
```

## 1. Monitor Checking Flow

```mermaid
flowchart TD
    Start([Start: Scheduler runs every second]) --> GetMonitors[Get monitors due for check]
    GetMonitors --> CheckEmpty{Any monitors?}
    CheckEmpty -->|No| End([End])
    CheckEmpty -->|Yes| Loop[For each monitor]
    
    Loop --> CheckType{Monitor Type?}
    
    CheckType -->|HTTP/HTTPS| HTTPCheck[Send HTTP request]
    CheckType -->|Port| PortCheck[Check TCP connection]
    CheckType -->|SSL| SSLCheck[Check SSL certificate]
    
    HTTPCheck --> EvalHTTP{Response OK?}
    PortCheck --> EvalPort{Port open?}
    SSLCheck --> EvalSSL{SSL valid?}
    
    EvalHTTP -->|Yes| Success[Status: UP]
    EvalHTTP -->|No| Fail[Status: DOWN]
    EvalPort -->|Yes| Success
    EvalPort -->|No| Fail
    EvalSSL -->|Yes| Success
    EvalSSL -->|No| Fail
    
    Success --> SaveLog[(Save monitoring log)]
    Fail --> SaveLog
    
    SaveLog --> CheckStatus{Status changed?}
    CheckStatus -->|Yes| TriggerIncident[Create/Resolve Incident]
    CheckStatus -->|No| UpdateMetrics[Update metrics]
    
    TriggerIncident --> QueueNotif[/Queue notification job/]
    QueueNotif --> UpdateMetrics
    UpdateMetrics --> NextMonitor{More monitors?}
    
    NextMonitor -->|Yes| Loop
    NextMonitor -->|No| End
```

## 2. Incident Detection Flow

```mermaid
flowchart TD
    Start([Start: Monitor status changed]) --> GetCurrent[Get current status]
    GetCurrent --> CheckDown{Status = DOWN?}
    
    CheckDown -->|Yes| CheckExisting{Active incident exists?}
    CheckDown -->|No| CheckIncident{Active incident exists?}
    
    CheckExisting -->|No| CreateIncident[Create new incident]
    CheckExisting -->|Yes| UpdateIncident[Update incident]
    
    CheckIncident -->|Yes| ResolveIncident[Resolve incident]
    CheckIncident -->|No| NoAction[No action needed]
    
    CreateIncident --> SetPriority[Set priority based on monitor]
    SetPriority --> SaveIncident[(Save to database)]
    SaveIncident --> NotifyDown[/Queue DOWN notification/]
    
    ResolveIncident --> CalcDowntime[Calculate downtime duration]
    CalcDowntime --> SaveResolved[(Update incident status)]
    SaveResolved --> NotifyUp[/Queue RECOVERY notification/]
    
    UpdateIncident --> NoNotif[No notification]
    NoAction --> End([End])
    NoNotif --> End
    NotifyDown --> End
    NotifyUp --> End
```

## 3. Notification Processing Flow

```mermaid
flowchart TD
    Start([Start: Queue worker picks job]) --> GetIncident[Get incident data]
    GetIncident --> GetChannels[Get enabled notification channels]
    GetChannels --> CheckChannels{Channels exist?}
    
    CheckChannels -->|No| End([End])
    CheckChannels -->|Yes| LoopChannels[For each channel]
    
    LoopChannels --> CheckType{Channel Type?}
    
    CheckType -->|Email| SendEmail[Send email via SMTP]
    CheckType -->|Telegram| SendTelegram[Send via Telegram API]
    CheckType -->|Slack| SendSlack[Send via Slack webhook]
    
    SendEmail --> CheckSuccess{Success?}
    SendTelegram --> CheckSuccess
    SendSlack --> CheckSuccess
    
    CheckSuccess -->|Yes| LogSuccess[(Log notification sent)]
    CheckSuccess -->|No| LogFail[(Log notification failed)]
    
    LogSuccess --> NextChannel{More channels?}
    LogFail --> Retry{Retry attempts left?}
    
    Retry -->|Yes| DelayRetry[Delay and retry]
    Retry -->|No| NextChannel
    
    DelayRetry --> CheckType
    NextChannel -->|Yes| LoopChannels
    NextChannel -->|No| End
```

## 4. API Request Flow

```mermaid
flowchart TD
    Start([Start: API Request]) --> Auth{Authenticated?}
    
    Auth -->|No| Return401[/Return 401 Unauthorized/]
    Auth -->|Yes| ValidateJWT[Validate JWT token]
    
    ValidateJWT --> JWTValid{Token valid?}
    JWTValid -->|No| Return401
    JWTValid -->|Yes| CheckRoute{Route?}
    
    CheckRoute -->|GET /monitors| GetMonitors[(Fetch monitors from DB)]
    CheckRoute -->|POST /monitors| CreateMonitor[Validate & create monitor]
    CheckRoute -->|GET /incidents| GetIncidents[(Fetch incidents from DB)]
    CheckRoute -->|GET /statistics| GetStats[Calculate statistics]
    CheckRoute -->|Other| RouteHandler[Handle specific route]
    
    GetMonitors --> ReturnSuccess[/Return 200 with data/]
    CreateMonitor --> SaveDB[(Save to database)]
    GetIncidents --> ReturnSuccess
    GetStats --> AggregateData[Aggregate from metrics]
    RouteHandler --> ProcessLogic[Process business logic]
    
    SaveDB --> ReturnSuccess
    AggregateData --> ReturnSuccess
    ProcessLogic --> ReturnSuccess
    
    Return401 --> End([End])
    ReturnSuccess --> End
```

## 5. Queue Job Processing

```mermaid
flowchart TD
    Start([Start: Worker started]) --> Connect[Connect to Redis]
    Connect --> Listen[Listen to queue]
    
    Listen --> JobAvail{Job available?}
    JobAvail -->|No| Wait[Wait for job]
    Wait --> Listen
    
    JobAvail -->|Yes| FetchJob[/Fetch job from queue/]
    FetchJob --> Deserialize[Deserialize job data]
    
    Deserialize --> Execute{Job Type?}
    Execute -->|SendNotification| NotifHandler[NotificationHandler]
    Execute -->|OtherJob| OtherHandler[Specific handler]
    
    NotifHandler --> Process[Process notification]
    OtherHandler --> Process
    
    Process --> Success{Successful?}
    Success -->|Yes| DeleteJob[(Delete job from queue)]
    Success -->|No| CheckRetry{Max retries?}
    
    CheckRetry -->|Reached| MoveToFailed[(Move to failed jobs)]
    CheckRetry -->|Not reached| Requeue[/Requeue with delay/]
    
    DeleteJob --> Listen
    MoveToFailed --> Listen
    Requeue --> Listen
```

## 6. Metrics Aggregation Flow

```mermaid
flowchart TD
    Start([Start: Scheduled aggregation]) --> GetInterval{Interval?}
    
    GetInterval -->|Minute| GetLastMinute[(Get logs from last minute)]
    GetInterval -->|Hour| GetLastHour[(Get minute aggregates from last hour)]
    GetInterval -->|Day| GetLastDay[(Get hour aggregates from last day)]
    
    GetLastMinute --> CalcMetrics[Calculate uptime %, response time]
    GetLastHour --> CalcMetrics
    GetLastDay --> CalcMetrics
    
    CalcMetrics --> SaveAggr[(Save to metrics_aggregates table)]
    SaveAggr --> CheckCleanup{Should cleanup old data?}
    
    CheckCleanup -->|Yes| DeleteOld[(Delete old raw logs)]
    CheckCleanup -->|No| End([End])
    
    DeleteOld --> End
```

## System Architecture

```mermaid
graph LR
    Client[Frontend Vue.js] -->|API Calls| LB[Load Balancer]
    LB --> API1[Laravel Instance 1]
    LB --> API2[Laravel Instance 2]
    LB --> API3[Laravel Instance 3]
    
    API1 --> DB[(PostgreSQL)]
    API2 --> DB
    API3 --> DB
    
    API1 --> Redis[(Redis)]
    API2 --> Redis
    API3 --> Redis
    
    Redis -->|Queue| Worker1[Queue Worker 1]
    Redis -->|Queue| Worker2[Queue Worker 2]
    Redis -->|Cache| API1
    
    Scheduler[Laravel Scheduler] -->|Every second| MonitorCheck[Monitor Check Command]
    MonitorCheck --> DB
    
    Worker1 -->|Send| Email[Email Service]
    Worker1 -->|Send| Telegram[Telegram API]
    Worker2 -->|Send| Slack[Slack Webhook]
```

## Flowchart Symbol Legend

| Symbol | Shape | Description |
|--------|-------|-------------|
| `([...])` | Oval/Terminator | Start/End program |
| `[...]` | Rectangle | Process/Action |
| `{...}` | Diamond | Decision/Condition |
| `[(...))]` | Cylinder | Database operation |
| `[/..../]` | Parallelogram | Input/Output |

## Complete System Flow Summary

### Monitor Creation Flow
1. **Frontend** - User fills form and submits
2. **API** - Validates and saves to database
3. **Database** - Stores monitor configuration
4. **Response** - Frontend shows success/error

### Monitor Checking Flow
1. **Scheduler** - Runs every second, queries due monitors
2. **Check Execution** - HTTP/Port/SSL check performed
3. **Database** - Save log and update monitor status
4. **Incident Detection** - Create/resolve incidents on status change
5. **Queue** - Notification job queued

### Notification Flow
1. **Queue Worker** - Picks job from Redis
2. **Send** - Delivers to Email/Telegram/Slack
3. **Log** - Records notification result

### Frontend Updates
1. **Polling** - Frontend requests updates every 10 seconds
2. **API** - Returns latest monitor status
3. **UI** - Updates dashboard display
