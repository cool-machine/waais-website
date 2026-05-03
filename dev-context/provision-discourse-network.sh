#!/usr/bin/env bash
# Provision Sweden Central RG + networking for the Discourse VM.
# Idempotent — safe to re-run; az create commands are upsert.
set -euo pipefail
AZ=/opt/homebrew/bin/az
RG=rg-waais-discourse-swc
LOC=swedencentral
NSG=nsg-waais-discourse-swc
VNET=vnet-waais-discourse-swc
SUBNET=snet-discourse
PIP=pip-waais-discourse-swc

echo "--- create RG ---"
$AZ group create -n "$RG" -l "$LOC" \
  --tags project=waais slice=discourse owner=george environment=prod \
  -o table

echo "--- create NSG ---"
$AZ network nsg create -g "$RG" -n "$NSG" -l "$LOC" \
  --tags project=waais slice=discourse -o none

for rule in "allow-ssh:1000:22" "allow-http:1010:80" "allow-https:1020:443"; do
  IFS=":" read -r name prio port <<< "$rule"
  $AZ network nsg rule create -g "$RG" --nsg-name "$NSG" -n "$name" \
    --priority "$prio" --access Allow --direction Inbound --protocol Tcp \
    --source-address-prefixes Internet --destination-port-ranges "$port" \
    -o none
done

echo "--- create VNet + subnet ---"
$AZ network vnet create -g "$RG" -n "$VNET" -l "$LOC" \
  --address-prefixes 10.10.0.0/16 \
  --subnet-name "$SUBNET" --subnet-prefixes 10.10.0.0/24 \
  --tags project=waais slice=discourse -o none
$AZ network vnet subnet update -g "$RG" --vnet-name "$VNET" \
  -n "$SUBNET" --network-security-group "$NSG" -o none

echo "--- create static public IPv4 ---"
$AZ network public-ip create -g "$RG" -n "$PIP" -l "$LOC" \
  --sku Standard --allocation-method Static --version IPv4 \
  --tags project=waais slice=discourse -o none

echo "--- networking summary ---"
$AZ network public-ip show -g "$RG" -n "$PIP" \
  --query '{ip:ipAddress,sku:sku.name,allocation:publicIPAllocationMethod}' \
  -o json
