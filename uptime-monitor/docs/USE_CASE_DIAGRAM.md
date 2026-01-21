# Frontend Use Case Diagram

```mermaid
graph TB
    User((User))
    Admin((Admin))
    
    subgraph "Uptime Monitor Frontend"
        UC1[Login/Register]
        UC2[View Dashboard]
        UC3[Manage Monitors]
        UC4[View Monitor Details]
        UC5[View Incidents]
        UC6[View Notifications]
        UC7[Configure Settings]
        UC8[Manage Notification Channels]
        UC9[View Statistics/Reports]
        UC10[Manage Users]
    end
    
    User --> UC1
    User --> UC2
    User --> UC3
    User --> UC4
    User --> UC5
    User --> UC6
    User --> UC9
    
    Admin --> UC1
    Admin --> UC2
    Admin --> UC3
    Admin --> UC4
    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    Admin --> UC9
    Admin --> UC10
```

## Use Cases

### User
1. **Login/Register** - Authenticate to system
2. **View Dashboard** - See all monitors status and uptime
3. **Manage Monitors** - Create, edit, delete monitors
4. **View Monitor Details** - Check specific monitor statistics
5. **View Incidents** - See incident history
6. **View Notifications** - Check notification logs
7. **View Statistics/Reports** - Analyze uptime data

### Admin
All User capabilities plus:
8. **Configure Settings** - System configuration
9. **Manage Notification Channels** - Email, Telegram, Slack setup
10. **Manage Users** - User management

## Actor Relationships

```mermaid
classDiagram
    User <|-- Admin
    
    class User {
        +login()
        +viewDashboard()
        +manageMonitors()
        +viewIncidents()
        +viewNotifications()
    }
    
    class Admin {
        +configureSettings()
        +manageChannels()
        +manageUsers()
    }
```
