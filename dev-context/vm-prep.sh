#!/usr/bin/env bash
# Prep the Discourse VM: docker, swap, fail2ban, clone discourse_docker.
# Runs ON the VM as the waaisops user via ssh (uses sudo).
# Idempotent — safe to re-run.
set -euo pipefail

echo "=== 1/6 apt update + base packages ==="
sudo DEBIAN_FRONTEND=noninteractive apt-get update -y
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
  ca-certificates curl gnupg git fail2ban unattended-upgrades \
  jq dnsutils

echo "=== 2/6 docker (official repo) ==="
if ! command -v docker >/dev/null 2>&1; then
  sudo install -m 0755 -d /etc/apt/keyrings
  sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    -o /etc/apt/keyrings/docker.asc
  sudo chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
    | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
  sudo DEBIAN_FRONTEND=noninteractive apt-get update -y
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
    docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
sudo systemctl enable --now docker
sudo usermod -aG docker waaisops

echo "=== 3/6 2 GB swapfile ==="
if [ ! -f /swapfile ]; then
  sudo fallocate -l 2G /swapfile
  sudo chmod 600 /swapfile
  sudo mkswap /swapfile
  sudo swapon /swapfile
  echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab > /dev/null
fi
sudo sysctl -w vm.swappiness=10 >/dev/null
echo 'vm.swappiness=10' | sudo tee /etc/sysctl.d/99-swappiness.conf > /dev/null

echo "=== 4/6 fail2ban (SSH defaults) ==="
sudo systemctl enable --now fail2ban
sudo systemctl status fail2ban --no-pager | head -3 || true

echo "=== 5/6 unattended-upgrades ==="
echo 'APT::Periodic::Update-Package-Lists "1";' | sudo tee /etc/apt/apt.conf.d/20auto-upgrades > /dev/null
echo 'APT::Periodic::Unattended-Upgrade "1";' | sudo tee -a /etc/apt/apt.conf.d/20auto-upgrades > /dev/null

echo "=== 6/6 clone discourse_docker ==="
if [ ! -d /var/discourse ]; then
  sudo git clone https://github.com/discourse/discourse_docker.git /var/discourse
fi
sudo chown -R waaisops:waaisops /var/discourse
cd /var/discourse
git fetch origin && git checkout main && git pull --ff-only

echo "=== DONE. Versions: ==="
echo "docker: $(docker --version 2>&1 || echo 'docker not yet on path; new shell needed')"
echo "discourse_docker HEAD: $(git -C /var/discourse rev-parse --short HEAD)"
echo "swap: $(free -h | awk '/Swap/ {print $2}')"
echo "disk free: $(df -h / | awk 'NR==2 {print $4}')"
