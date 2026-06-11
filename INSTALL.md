# Installation Guide

This guide walks you through setting up a hardened Ubuntu 24.04 LTS server with nginx as a reverse proxy, Docker, and the **Ledningssystemet** application — including automatic daily updates.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Harden Ubuntu 24.04 LTS](#2-harden-ubuntu-2404-lts)
3. [Install nginx](#3-install-nginx)
4. [Install Docker](#4-install-docker)
5. [Configure nginx as a Reverse Proxy](#5-configure-nginx-as-a-reverse-proxy)
6. [Pull and Run the Application](#6-pull-and-run-the-application)
7. [Automatic Daily Updates](#7-automatic-daily-updates)

---

## 1. Prerequisites

- A fresh **Ubuntu 24.04 LTS** server (physical, VM, or VPS)
- A domain name pointed at the server's public IP address
- Root or `sudo` access
- Ports **80** and **443** open in the firewall

---

## 2. Harden Ubuntu 24.04 LTS

### 2.1 Update the system

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt autoremove -y
```

### 2.2 Create a non-root admin user (skip if already done)

```bash
sudo adduser deploy
sudo usermod -aG sudo deploy
```

### 2.3 Configure SSH hardening

```bash
sudo nano /etc/ssh/sshd_config
```

Set or verify the following values:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
X11Forwarding no
AllowTcpForwarding no
MaxAuthTries 3
```

Restart SSH:

```bash
sudo systemctl restart ssh
```

### 2.4 Configure UFW firewall

```bash
sudo apt install -y ufw
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
```

### 2.5 Install and configure Fail2ban

```bash
sudo apt install -y fail2ban
sudo systemctl enable --now fail2ban
```

### 2.6 Enable automatic security updates

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

### 2.7 Disable unused services

```bash
sudo systemctl disable --now avahi-daemon cups bluetooth 2>/dev/null || true
```

---

## 3. Install nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

Verify nginx is running:

```bash
curl -I http://localhost
```

### 3.1 Obtain a TLS certificate with Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example.com
```

Follow the prompts. Certbot will automatically configure HTTPS and set up automatic renewal.

---

## 4. Install Docker

```bash
# Install required packages
sudo apt install -y ca-certificates curl gnupg lsb-release

# Add Docker's official GPG key
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Add the Docker repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Engine
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Enable and start Docker
sudo systemctl enable --now docker

# Allow the deploy user to run Docker without sudo
sudo usermod -aG docker deploy
```

> **Note:** Log out and back in (or run `newgrp docker`) for the group change to take effect.

Verify Docker works:

```bash
docker run --rm hello-world
```

---

## 5. Configure nginx as a Reverse Proxy

Create a site configuration for the application. Replace `your-domain.example.com` with your actual domain.

```bash
sudo nano /etc/nginx/sites-available/ledningssystemet
```

Paste the following (Certbot will have already created the SSL section if you ran step 3.1 — adapt as needed):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.example.com;

    ssl_certificate     /etc/letsencrypt/live/your-domain.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.example.com/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    client_max_body_size 128M;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
}
```

Enable the site and reload nginx:

```bash
sudo ln -s /etc/nginx/sites-available/ledningssystemet /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Pull and Run the Application

The application is published as a Docker image on the GitHub Container Registry (ghcr.io).

### 6.1 Create environment configuration

```bash
sudo mkdir -p /opt/ledningssystemet
sudo nano /opt/ledningssystemet/.env
```

Minimum required variables (adjust to your environment):

```dotenv
APP_NAME=Ledningssystemet
APP_ENV=production
APP_KEY=                        # Generate with: docker run --rm ghcr.io/ledningssystemet/ledningssystemet php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://your-domain.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ledningssystemet
DB_USERNAME=ledningssystemet
DB_PASSWORD=your-strong-password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 6.1 Start the container

```bash
docker pull ghcr.io/ledningssystemet/ledningssystemet:latest

docker run -d \
  --name ledningssystemet \
  --restart unless-stopped \
  -p 127.0.0.1:8080:80 \
  --env-file /opt/ledningssystemet/.env \
  ghcr.io/ledningssystemet/ledningssystemet:latest
```

Verify the container is running:

```bash
docker ps
curl -I http://localhost:8080
```

---

## 7. Automatic Daily Updates

Create an update script that pulls the latest image and restarts the container only when a new version is available.

### 7.1 Create the update script

```bash
sudo nano /opt/ledningssystemet/update.sh
```

Paste the following:

```bash
#!/usr/bin/env bash
set -euo pipefail

IMAGE="ghcr.io/ledningssystemet/ledningssystemet:latest"
CONTAINER="ledningssystemet"
ENV_FILE="/opt/ledningssystemet/.env"
LOG="/var/log/ledningssystemet-update.log"

echo "$(date '+%Y-%m-%d %H:%M:%S') — Checking for updates..." >> "$LOG"

PULL_OUTPUT=$(docker pull "$IMAGE" 2>&1)
echo "$PULL_OUTPUT" >> "$LOG"

if echo "$PULL_OUTPUT" | grep -q "Status: Image is up to date"; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') — Already up to date. No restart needed." >> "$LOG"
    exit 0
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') — New image found. Restarting container..." >> "$LOG"

docker stop "$CONTAINER"  >> "$LOG" 2>&1 || true
docker rm   "$CONTAINER"  >> "$LOG" 2>&1 || true

docker run -d \
  --name "$CONTAINER" \
  --restart unless-stopped \
  -p 127.0.0.1:8080:80 \
  --env-file "$ENV_FILE" \
  "$IMAGE" >> "$LOG" 2>&1

# Remove unused images to free disk space
docker image prune -f >> "$LOG" 2>&1

echo "$(date '+%Y-%m-%d %H:%M:%S') — Update complete." >> "$LOG"
```

Make the script executable:

```bash
sudo chmod +x /opt/ledningssystemet/update.sh
```

### 7.2 Schedule with cron

```bash
sudo crontab -e
```

Add the following line to run the update every night at **03:00**:

```cron
0 3 * * * /opt/ledningssystemet/update.sh
```

### 7.3 Verify and test

Check the log after the first run:

```bash
sudo tail -f /var/log/ledningssystemet-update.log
```

Trigger manually to test:

```bash
sudo /opt/ledningssystemet/update.sh
```

---

## Summary

| Component | Purpose |
|-----------|---------|
| Ubuntu 24.04 LTS (hardened) | Secure base OS |
| nginx | TLS termination and reverse proxy |
| Docker | Container runtime for the application |
| ghcr.io image | Pre-built application package from GitHub |
| update.sh + cron | Automatic daily updates at 03:00 |

For questions or issues, please open an issue in the [GitHub repository](https://github.com/ledningssystemet/ledningssystemet).

