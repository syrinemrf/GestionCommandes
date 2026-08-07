Write-Host ""
Write-Host "=== Demarrage de Comdely ==="
Write-Host ""

# --------------------------------------------------
# 1. Verifier si Docker est disponible
# --------------------------------------------------

docker info *> $null

if ($LASTEXITCODE -ne 0) {

    Write-Host "Docker n'est pas demarre."
    Write-Host "Demarrage de Docker Desktop..."

    $dockerDesktop = "C:\Program Files\Docker\Docker\Docker Desktop.exe"

    if (-not (Test-Path $dockerDesktop)) {
        Write-Error "Docker Desktop est introuvable : $dockerDesktop"
        exit 1
    }

    Start-Process $dockerDesktop

    Write-Host "Attente du moteur Docker..."

    do {
        Start-Sleep -Seconds 3
        docker info *> $null
    }
    until ($LASTEXITCODE -eq 0)
}

Write-Host "Docker est pret."

# --------------------------------------------------
# 2. Configuration Docker Compose
# --------------------------------------------------

$compose = @(
    "-f", "compose.yaml",
    "-f", "data-platform/airflow/compose.airflow.yaml",
    "--env-file", "data-platform/airflow/.env"
)

# --------------------------------------------------
# 3. Demarrer le Data Warehouse et Airflow
# --------------------------------------------------

Write-Host "Demarrage du Data Warehouse et d'Airflow..."

docker compose @compose --profile airflow up -d `
    warehouse `
    airflow-postgres `
    airflow-apiserver `
    airflow-scheduler `
    airflow-dag-processor

if ($LASTEXITCODE -ne 0) {
    Write-Error "Impossible de demarrer l'infrastructure Docker."
    exit 1
}

# --------------------------------------------------
# 4. Attendre PostgreSQL
# --------------------------------------------------

Write-Host "Attente du Data Warehouse..."

do {
    Start-Sleep -Seconds 2

    docker compose @compose exec -T warehouse `
        pg_isready `
        -U comdely_dw `
        -d comdely_dw *> $null
}
until ($LASTEXITCODE -eq 0)

Write-Host "Data Warehouse pret."

# --------------------------------------------------
# 5. Afficher l'etat des services
# --------------------------------------------------

docker compose @compose --profile airflow ps

# --------------------------------------------------
# 6. Demarrer Symfony
# --------------------------------------------------

Write-Host ""
Write-Host "Infrastructure prete."
Write-Host "Demarrage de Symfony..."
Write-Host ""

symfony server:start --no-tls