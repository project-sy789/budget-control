# Budget Control System - Deployment Guide

This guide explains how to deploy the Budget Control PHP application on various cloud platforms.

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for dependency management)
- Git repository access

## Platform-Specific Deployment

### 1. Render Deployment

#### Step 1: Prepare Your Repository
1. Push your code to GitHub/GitLab
2. Ensure `render.yaml` is in your repository root

#### Step 2: Create Database
1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click "New" → "PostgreSQL" or "MySQL"
3. Name: `budget-control-db`
4. Note the connection details

#### Step 3: Deploy Web Service
1. Click "New" → "Web Service"
2. Connect your repository
3. Select the `Budget-control-v2` directory
4. Render will automatically detect the `render.yaml` configuration
5. Click "Create Web Service"

#### Step 4: Run Database Setup
1. Access your web service URL
2. Navigate to `/install.php`
3. Follow the installation wizard

### 2. Railway Deployment

#### Step 1: Install Railway CLI
```bash
npm install -g @railway/cli
railway login
```

#### Step 2: Deploy
```bash
cd Budget-control-v2
railway deploy
```

#### Step 3: Add MySQL Plugin
1. Go to Railway dashboard
2. Add MySQL plugin to your project
3. Note the connection variables

#### Step 4: Set Environment Variables
Railway will automatically set database variables. Additional variables:
```
APP_ENV=production
PHP_VERSION=8.1
```

### 3. Heroku Deployment

#### Step 1: Install Heroku CLI
Download from [Heroku CLI](https://devcenter.heroku.com/articles/heroku-cli)

#### Step 2: Create App
```bash
cd Budget-control-v2
heroku create your-app-name
heroku addons:create cleardb:ignite
```

#### Step 3: Configure Environment
```bash
heroku config:set APP_ENV=production
heroku config:set PHP_VERSION=8.1
```

#### Step 4: Deploy
```bash
git add .
git commit -m "Deploy to Heroku"
git push heroku main
```

### 4. Traditional Hosting (cPanel/Shared Hosting)

#### Step 1: Upload Files
1. Upload all files from `Budget-control-v2/` to your hosting directory
2. Ensure `public/` folder is your document root

#### Step 2: Create Database
1. Create MySQL database via cPanel
2. Note database name, username, and password

#### Step 3: Install
1. Visit `your-domain.com/install.php`
2. Follow installation wizard

## Post-Deployment Steps

### 1. Security
- Delete `install.php` after successful installation
- Change default admin password
- Set proper file permissions (755 for directories, 644 for files)

### 2. File Uploads
Ensure the uploads directory is writable:
```bash
chmod 755 public/uploads
```

### 3. Database Backup
Set up regular database backups through your hosting provider or use automated tools.

## Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|----------|
| `DB_HOST` | Database host | `localhost` |
| `DB_NAME` | Database name | `budget_control` |
| `DB_USER` | Database username | `budget_user` |
| `DB_PASSWORD` | Database password | `secure_password` |
| `DB_PORT` | Database port | `3306` |
| `APP_ENV` | Application environment | `production` |
| `PHP_VERSION` | PHP version | `8.1` |

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials
   - Check if database server is running
   - Ensure database exists

2. **File Permission Errors**
   - Set proper permissions on uploads directory
   - Ensure web server can write to necessary directories

3. **Composer Dependencies**
   - Run `composer install --no-dev` in production
   - Ensure all required PHP extensions are installed

4. **Session Issues**
   - Check session configuration in hosting environment
   - Ensure session directory is writable

### Support

For deployment issues:
1. Check platform-specific documentation
2. Verify all environment variables are set correctly
3. Check application logs for detailed error messages

## Performance Optimization

### Production Settings
- Enable OPcache
- Use production-optimized Composer autoloader
- Configure proper caching headers
- Use CDN for static assets if needed

### Database Optimization
- Regular database maintenance
- Proper indexing
- Connection pooling for high-traffic sites