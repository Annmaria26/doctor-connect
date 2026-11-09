# Deploying DoctorConnect on Red Hat OpenShift

This guide provides instructions to deploy **DoctorConnect** on any Red Hat OpenShift Container Platform (ROKS, ARO, ROSA, CRC, or on-premise OpenShift 4.x).

---

## Prerequisites
1. **OpenShift CLI (`oc`)** installed on your computer.
2. Logged into your OpenShift cluster via CLI:
   ```bash
   oc login --server=https://api.your-cluster-domain.com:6443 --token=<YOUR_TOKEN>
   ```

---

## Method 1: Deploying the Standalone Frontend (Quickest, Zero DB Pods)

If you only want to deploy the frontend (no backend server or database pods needed):

### Step 1: Create a New OpenShift Project
```bash
oc new-project doctor-connect
```

### Step 2: Build the Container Image using OpenShift Internal Registry
Open your terminal in `C:\Users\annjo\OneDrive\Desktop\Optima` and run:
```bash
# 1. Create a binary build config
oc new-build --name=doctor-connect-frontend --strategy=docker --binary=true

# 2. Upload and build the container inside your cluster
oc start-build doctor-connect-frontend --from-file=openshift/Dockerfile.frontend --from-dir=. --follow
```

### Step 3: Deploy the Frontend, Service, and HTTPS Route
```bash
# Deploy using the built image
oc new-app doctor-connect-frontend:latest

# Expose as a secure HTTPS public route
oc create route edge doctor-connect --service=doctor-connect-frontend --port=8080
```

### Step 4: Get Your Live Public Application URL
```bash
oc get route doctor-connect
```
*Open the resulting URL (e.g. `https://doctor-connect-doctor-connect.apps.your-cluster.com`) in your browser.*

---

## Method 2: Deploying the Full-Stack PHP + MySQL App on OpenShift

If you want to run the full PHP 8.2 backend with MySQL:

### Step 1: Create a Project
```bash
oc new-project doctor-connect
```

### Step 2: Deploy MySQL Database
Use the certified OpenShift MySQL template:
```bash
oc new-app mysql-persistent \
  -p MYSQL_USER=doctor_user \
  -p MYSQL_PASSWORD=DoctorUserSecret99! \
  -p MYSQL_DATABASE=doctor_connect \
  -p VOLUME_CAPACITY=2Gi
```

### Step 3: Build the PHP Application Container
Run an OpenShift binary build from the project directory:
```bash
oc new-build --name=doctor-connect-app --strategy=docker --binary=true
oc start-build doctor-connect-app --from-file=openshift/Dockerfile.fullstack --from-dir=. --follow
```

### Step 4: Deploy PHP Application Connected to MySQL
```bash
oc new-app doctor-connect-app:latest \
  -e DB_DRIVER=mysql \
  -e DB_HOST=mysql \
  -e DB_PORT=3306 \
  -e DB_NAME=doctor_connect \
  -e DB_USER=doctor_user \
  -e DB_PASS=DoctorUserSecret99!

# Expose route with automatic TLS certificate
oc create route edge doctor-connect-app --service=doctor-connect-app --port=8080
```

### Step 5: Initialize the Database (1-Click)
Once deployed, open your route URL in the browser and navigate to:
```
https://<YOUR-ROUTE-HOSTNAME>/setup.php
```
Click **"Go to Setup & Migration Wizard"** to automatically initialize tables, the UNIQUE race-safe token constraints, and seed data.

---

## Useful OpenShift Commands

- **Check Pod Status**: `oc get pods -w`
- **View Container Logs**: `oc logs -f deployment/doctor-connect-app`
- **Access Container Terminal**: `oc rsh deployment/doctor-connect-app`
- **Scale Replicas**: `oc scale deployment/doctor-connect-app --replicas=3`
