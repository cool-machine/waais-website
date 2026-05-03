#!/usr/bin/env bash
# Provision the Discourse VM in rg-waais-discourse-swc / Sweden Central.
# Standard_B2as_v2 (2 vCPU / 8 GB / AMD burstable / ~EUR 38/mo).
# 64 GB Premium SSD OS disk; static public IP and NSG were created earlier.
set -euo pipefail
AZ=/opt/homebrew/bin/az
RG=rg-waais-discourse-swc
LOC=swedencentral
VM=vm-waais-discourse-swc
NIC=nic-waais-discourse-swc
VNET=vnet-waais-discourse-swc
SUBNET=snet-discourse
PIP=pip-waais-discourse-swc
NSG=nsg-waais-discourse-swc
ADMIN=waaisops
KEY=/Users/gg1900/.ssh/id_ed25519.pub

echo "--- create NIC (with public IP, NSG already applied at subnet) ---"
$AZ network nic create -g "$RG" -n "$NIC" -l "$LOC" \
  --vnet-name "$VNET" --subnet "$SUBNET" \
  --public-ip-address "$PIP" \
  --tags project=waais slice=discourse -o none

echo "--- create VM ---"
$AZ vm create -g "$RG" -n "$VM" -l "$LOC" \
  --size Standard_B2as_v2 \
  --image Ubuntu2204 \
  --admin-username "$ADMIN" \
  --ssh-key-values "$KEY" \
  --nics "$NIC" \
  --os-disk-size-gb 64 \
  --os-disk-name "osdisk-${VM}" \
  --storage-sku Premium_LRS \
  --tags project=waais slice=discourse environment=prod \
  -o table

echo "--- VM summary ---"
$AZ vm show -d -g "$RG" -n "$VM" \
  --query '{name:name,size:hardwareProfile.vmSize,os:storageProfile.imageReference,publicIp:publicIps,privateIp:privateIps,powerState:powerState}' \
  -o json
