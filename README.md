# Budget Control Application (Rewritten)

## Overview

This project is a rewritten version of the Budget Control application, originally built with React and an Express backend using an in-memory database. This version utilizes a PostgreSQL database for persistent storage, incorporates Google OAuth for authentication, and includes various improvements and fixes.

**Thai:** โปรเจกต์นี้เป็นเวอร์ชันที่เขียนขึ้นใหม่ของแอปพลิเคชัน Budget Control ซึ่งเดิมสร้างด้วย React และ Express backend โดยใช้ฐานข้อมูลในหน่วยความจำ เวอร์ชันนี้ใช้ฐานข้อมูล PostgreSQL สำหรับการจัดเก็บข้อมูลถาวร, ใช้ Google OAuth สำหรับการยืนยันตัวตน และมีการปรับปรุงแก้ไขข้อผิดพลาดต่างๆ

**Key Features:**

*   **User Authentication:** Secure login via Google OAuth.
*   **User Management (Admin):** Admins can approve new users, manage roles (admin/user), and delete users.
*   **Project Management:** Create, Read, Update, Delete (CRUD) projects.
*   **Transaction Management:** CRUD operations for income/expense transactions linked to projects, with pagination.
*   **Budget Summary:** View overall budget summary and per-project summaries.
*   **Excel Export:** Export transactions for a selected project to an Excel file.
*   **Database:** Uses PostgreSQL for data persistence.
*   **Deployment:** Configured for deployment on Render.com (using `render.yaml`).
*   **Code Comments:** Includes detailed comments and Thai explanations for major components.

## Project Structure

The project is organized into two main directories:

*   `/client`: Contains the React frontend application.
*   `/server`: Contains the Node.js/Express backend API.

```
budget_control_rewrite/
├── client/             # React Frontend
│   ├── public/
│   ├── src/
│   │   ├── components/   # UI Components (Login, Layout, ProjectMgmt, etc.)
│   │   ├── contexts/     # React Contexts (Auth, Budget)
│   │   ├── services/     # API interaction services (AuthService)
│   │   ├── types/        # TypeScript type definitions
│   │   ├── App.tsx       # Main application component & routing
│   │   ├── index.tsx     # Entry point
│   │   └── theme.ts      # MUI theme configuration
│   ├── .env.example    # Example environment variables for client
│   ├── package.json
│   └── ...             # Other config files (tsconfig, etc.)
├── server/             # Node.js/Express Backend
│   ├── config/         # Configuration (db connection, jwt, port)
│   ├── controllers/    # Route handlers (logic for requests)
│   ├── middleware/     # Express middleware (auth, error handling)
│   ├── models/         # Database interaction logic (User, Project, Transaction)
│   ├── routes/         # API route definitions
│   ├── services/       # Business logic services (if needed)
│   ├── utils/          # Utility functions
│   ├── .env.example    # Example environment variables for server
│   ├── package.json
│   ├── server.js       # Main backend application entry point
│   └── ...
├── render.yaml         # Deployment configuration for Render.com
├── schema.sql          # PostgreSQL database schema
└── README.md           # This file
```

## Technology Stack

*   **Frontend:** React, TypeScript, Material UI (MUI), React Router, Axios (or Fetch API), Google OAuth Library (`@react-oauth/google`), Date-fns, JWT Decode
*   **Backend:** Node.js, Express, PostgreSQL (`pg` library), JWT (`jsonwebtoken`), Google Auth Library (`google-auth-library`), CORS, Dotenv, XLSX (for Excel export)
*   **Database:** PostgreSQL
*   **Deployment:** Render.com (Static Site + Web Service + PostgreSQL)

## Setup and Installation

**Prerequisites:**

*   Node.js (v18 or later recommended)
*   npm or yarn
*   PostgreSQL database instance (local or cloud-based)
*   Google Cloud Platform project with OAuth 2.0 Client ID configured.

**Steps:**

1.  **Clone the Repository:**
    ```bash
    # Not applicable in this context, assuming code is provided directly
    ```

2.  **Backend Setup:**
    *   Navigate to the `server` directory: `cd server`
    *   Install dependencies: `npm install`
    *   Create a `.env` file by copying `.env.example`:
        ```bash
        cp .env.example .env
        ```
    *   **Configure `.env` variables (see Environment Variables section below).** Crucially, set `DATABASE_URL`, `GOOGLE_CLIENT_ID`, and `JWT_SECRET`.
    *   Apply the database schema: Connect to your PostgreSQL database and run the commands in `schema.sql`.
        ```sql
        -- Example using psql
        -- psql -U your_db_user -d your_db_name -a -f ../schema.sql
        ```

3.  **Frontend Setup:**
    *   Navigate to the `client` directory: `cd ../client`
    *   Install dependencies: `npm install`
    *   Create a `.env` file by copying `.env.example`:
        ```bash
        cp .env.example .env
        ```
    *   **Configure `.env` variables (see Environment Variables section below).** Set `REACT_APP_GOOGLE_CLIENT_ID` and `REACT_APP_API_URL` (for local development).

4.  **Running Locally:**
    *   **Start Backend:** In the `server` directory, run: `npm run dev` (uses nodemon for auto-restarts) or `npm start`.
    *   **Start Frontend:** In the `client` directory, run: `npm start`.
    *   Open your browser to `http://localhost:3000` (or the port specified by React).

## Environment Variables

**Thai:** ตัวแปรสภาพแวดล้อมที่จำเป็นสำหรับการตั้งค่าโปรเจกต์

**Server (`/server/.env`):**

*   `NODE_ENV`: Set to `development` or `production`.
*   `PORT`: Port for the backend server (e.g., `5000`).
*   `DATABASE_URL`: Connection string for your PostgreSQL database.
    *   Format: `postgresql://DB_USER:DB_PASSWORD@DB_HOST:DB_PORT/DB_NAME`
*   `GOOGLE_CLIENT_ID`: Your Google OAuth Client ID (obtained from Google Cloud Console).
*   `JWT_SECRET`: A strong, secret string used for signing JWT tokens (generate a random one).
*   `JWT_EXPIRES_IN`: JWT token expiration time (e.g., `7d`, `24h`).

**Client (`/client/.env`):**

*   `REACT_APP_GOOGLE_CLIENT_ID`: Your Google OAuth Client ID (same as the server one).
*   `REACT_APP_API_URL`: The base URL of your backend API.
    *   For local development: `http://localhost:5000/api` (use the port your backend is running on).
    *   For production (Render): This will be set automatically by Render based on `render.yaml`.

## Database

*   The database schema is defined in `schema.sql`. Run this script against your PostgreSQL database to create the necessary tables (`users`, `projects`, `transactions`).
*   The backend connects to the database using the `DATABASE_URL` environment variable.

## Deployment (Render.com)

This project includes a `render.yaml` file for easy deployment on Render.com.

**Steps:**

1.  **Create a Render Account:** Sign up at [render.com](https://render.com/).
2.  **Create a New Blueprint Instance:**
    *   Go to "Blueprints" and click "New Blueprint Instance".
    *   Connect your Git repository (GitHub, GitLab, Bitbucket) containing this project.
    *   Render will automatically detect `render.yaml`.
3.  **Configure Services:**
    *   Render will propose services based on `render.yaml` (backend, frontend, database).
    *   **Database:** Ensure the PostgreSQL database (`budget-control-db`) is configured (choose a plan, region, etc.).
    *   **Backend (`budget-control-backend`):**
        *   Verify the build and start commands.
        *   Go to the "Environment" tab.
        *   The `DATABASE_URL` should be automatically linked from the database service.
        *   The `JWT_SECRET` will be generated by Render (or you can set your own).
        *   **Crucially, add a secret environment variable for `GOOGLE_CLIENT_ID` with your actual Google Client ID.**
    *   **Frontend (`budget-control-frontend`):**
        *   Verify the build command and publish directory.
        *   Go to the "Environment" tab.
        *   The `REACT_APP_API_URL` should be automatically linked from the backend service.
        *   **Crucially, add a secret environment variable for `REACT_APP_GOOGLE_CLIENT_ID` with your actual Google Client ID.**
4.  **Deploy:** Click "Create Blueprint Instance" or "Deploy". Render will build and deploy your services.
5.  **Access:** Once deployed, Render will provide public URLs for your frontend and backend.

**Important Notes for Deployment:**

*   **Environment Variables:** Ensure all required environment variables, especially secrets like `GOOGLE_CLIENT_ID` and `JWT_SECRET`, are set correctly in the Render dashboard environment sections for both the backend and frontend services.
*   **Database Connection:** The `DATABASE_URL` is automatically provided by Render when linking the database service.
*   **Google OAuth Configuration:** Make sure your Google Cloud OAuth Client ID has the correct authorized JavaScript origins (for the frontend URL provided by Render) and authorized redirect URIs (if applicable, though this setup uses token-based flow).

## Code Explanations (Thai)

Detailed explanations in Thai are provided as comments within the source code files for major components, including:

*   Backend: `server.js`, controllers, models, middleware, routes, config.
*   Frontend: `App.tsx`, contexts (`AuthContext`, `BudgetContext`), services (`AuthService`), main components (`Login`, `Layout`, `ProjectManagement`, `BudgetControl`, etc.).

**Thai:** คำอธิบายโค้ดโดยละเอียดเป็นภาษาไทยมีอยู่ในรูปแบบคอมเมนต์ภายในไฟล์ซอร์สโค้ดสำหรับองค์ประกอบหลักต่างๆ ทั้งในส่วนของ Backend และ Frontend

