# Neuravolt VPS Migration Guide

This guide explains how to migrate the entire Neuravolt system (including configurations, database entries, secrets, and client n8n workflow storage volumes) from your current VPS to a new VPS.

---

## Migration Architecture

Since docker volumes, environment keys, and database records are not tracked in Git for security reasons, we use two helper scripts to package them:
1. **`scripts/vps_backup.sh`**: Runs on the **old VPS**. Dumps the database, grabs configs, grabs secrets, and bundles all user docker storage volumes (`nv-instance-*-data`) into a single archive file `neuravolt_pack_[TIMESTAMP].tar.gz`.
2. **`scripts/vps_restore.sh`**: Runs on the **new VPS**. Restores all environment variables, launches Postgres, imports the database, recreates client docker volumes, and boots the entire stack.

---

## Step 1: Create a Migration Pack (On the Current VPS)

1. Connect to your **current VPS**:
   ```bash
   ssh -i ~/Downloads/developer.pem ubuntu@45.194.2.244
   ```
2. Navigate to the hosting folder:
   ```bash
   cd /var/www/n8n_hosting
   ```
3. Give execution permissions and run the backup script:
   ```bash
   chmod +x scripts/vps_backup.sh
   ./scripts/vps_backup.sh
   ```
4. This will output a file named **`neuravolt_pack_[TIMESTAMP].tar.gz`** in the directory.

---

## Step 2: Download the Migration Pack to your Local Machine

On your **local machine terminal** (not the VPS), download the generated pack from the old VPS:
```bash
scp -i ~/Downloads/developer.pem ubuntu@45.194.2.244:/var/www/n8n_hosting/neuravolt_pack_*.tar.gz ~/Downloads/
```

---

## Step 3: Setup the New VPS & Clone Codebase

1. Configure DNS A-records for your domains (`neuravolt.cloud`, `*.neuravolt.cloud`) to point to your **new VPS IP Address**.
2. Connect to your **new VPS**:
   ```bash
   ssh -i <your_key.pem> ubuntu@<new_vps_ip>
   ```
3. Install Docker and Docker Compose on the new VPS.
4. Clone the repository into `/var/www/n8n_hosting`:
   ```bash
   git clone https://github.com/ajayprataptomar/n8n_selfhosting.git /var/www/n8n_hosting
   cd /var/www/n8n_hosting
   ```

---

## Step 4: Transfer the Migration Pack to the New VPS

On your **local machine terminal**, upload the package file to the new VPS:
```bash
scp -i <your_key.pem> ~/Downloads/neuravolt_pack_*.tar.gz ubuntu@<new_vps_ip>:/var/www/n8n_hosting/
```

---

## Step 5: Restore Everything (On the New VPS)

1. Connect to the **new VPS**:
   ```bash
   ssh -i <your_key.pem> ubuntu@<new_vps_ip>
   cd /var/www/n8n_hosting
   ```
2. Run the restore script, passing the pack file as an argument:
   ```bash
   chmod +x scripts/vps_restore.sh
   ./scripts/vps_restore.sh neuravolt_pack_*.tar.gz
   ```
3. The restore script will:
   * Recreate `.env` and `secrets/` folders.
   * Launch and import the Postgres database schema and user records.
   * Dynamically rebuild all client n8n docker volumes.
   * Boot up all Neuravolt service containers.
   * Clean up temporary files.

4. Verify all containers are running successfully:
   ```bash
   docker ps
   ```

---

## Clean Up

Once you confirm the new server is running perfectly, you can delete the backup `.tar.gz` files from both servers and your local downloads folder to free up space.
