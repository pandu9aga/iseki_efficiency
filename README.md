# Iseki Efficiency - Operational Efficiency & Tracking System

## Overview

**Iseki Efficiency** is an advanced operational management system designed to track and optimize efficiency across various operational metrics. It features a sophisticated role-based ecosystem (Admin, Leader, Member) to manage resources, including tractors, power usage, costs, and issue handling ("Penanganan").

The system emphasizes real-time tracking via scanning interfaces and comprehensive reporting dashboards for leaders and administrators.

## Key Features

### 1. Role-Based Module
*   **Admin**: Full system control, including User/Member management, Tractor/Area configuration, and master data oversight.
*   **Leader**: Team management, daily planning, detailed reporting (Cost, Power, Issues), and fullscreen dashboard views.
*   **Member**: Direct scanning interface, daily reporting, and performance tracking.

### 2. Operational Tracking
*   **Scanning System**:
    *   Public and Member-specific scanning endpoints.
    *   Verification workflows for area and member activities.
*   **Resource Monitoring**:
    *   **Cost**: Track operational costs.
    *   **Power**: Monitor power usage efficiency.
    *   **Penanganan**: Issue tracking and resolution logs.

### 3. Management & Planning
*   **Daily Planning**: Tools for Leaders and Admins to set daily operational goals.
*   **Job Management**: Assign and manage jobs for team members.
*   **Member Selection**: Dynamic member assignment and selection workflows.

### 4. Dashboards & Reporting
*   **Fullscreen Dashboards**: Optimized views for monitoring screens (Leaders/Admins).
*   **Exports**: Excel export capabilities for deep data analysis.
*   **DataTables**: High-performance data grids for navigating large datasets.

## Technology Stack

### Backend
*   **Framework**: [Laravel 12.x](https://laravel.com)
*   **Language**: PHP ^8.2
*   **Database**: MySQL / MariaDB (Default)
*   **Excel Processing**: `box/spout` (Fast, memory-efficient Excel streaming)
*   **DataTables**: `yajra/laravel-datatables` ^12.0

### Frontend
*   **Build Tool**: [Vite](https://vitejs.dev)
*   **Styling**: [Tailwind CSS v4.0](https://tailwindcss.com)
*   **HTTP Client**: Axios

## Installation & Setup

1.  **Clone the Repository**
    ```bash
    git clone <repository-url>
    cd iseki_efficiency
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    npm install
    ```

3.  **Environment Setup**
    *   Copy the `.env.example` file:
        ```bash
        cp .env.example .env
        ```
    *   Configure database connection in `.env`.

4.  **Database Migration**
    ```bash
    php artisan key:generate
    php artisan migrate
    ```

5.  **Build Assets**
    ```bash
    npm run build
    ```

6.  **Start Application**
    ```bash
    php artisan serve
    ```
    Access the application at `http://localhost:8000`.

## License

This project is proprietary.
