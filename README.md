# Church Events Manager

A WordPress plugin for managing church events with React Native app integration.

## Features

- Event Management
  - Add, edit, and delete events
  - Support for recurring events (daily, weekly, monthly)
  - Event categories and tags
  - Featured events
  - Location management with optional Google Maps integration

- RSVP System
  - Allow users to RSVP to events
  - Track attendance limits
  - RSVP status management (attending, not attending, maybe)

- Mobile App Integration
  - RESTful API endpoints
  - JWT authentication support
  - Real-time synchronization
  - Efficient caching system

- Notifications
  - Event reminders
  - New event notifications
  - Integration with Church App notification system

- Display Options
  - Widget with hover details
  - Shortcodes for event lists and calendars
  - Customizable templates

## Installation

1. Download the plugin zip file
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Activate the plugin

## Configuration

### General Settings

1. Go to Events > Settings in WordPress admin
2. Configure general settings:
   - Events per page
   - Default timezone
   - Enable/disable recurring events

### Notification Settings

1. Configure notification preferences:
   - Reminder time
   - Notification types
   - Default notification settings

### Google Maps Integration

1. Enable Google Maps integration
2. Add your Google Maps API key
3. Configure map display options

### Cache Settings

1. Set cache duration
2. Configure cache invalidation rules

## Usage

### Adding Events

1. Go to Events > Add New
2. Fill in event details:
   - Title
   - Description
   - Date and time
   - Location
   - Maximum attendees
3. Set recurring options if needed
4. Choose notification preferences
5. Publish the event

### Managing RSVPs

1. View RSVP list in event details
2. Export attendance lists
3. Send notifications to attendees

### Using Shortcodes 