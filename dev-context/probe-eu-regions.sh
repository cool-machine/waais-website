#!/usr/bin/env bash
# Probe B-series and Discourse-viable VM SKUs across every EU/UK Azure region
# to find one where the Sponsorship subscription can deploy small/cheap compute.
set -uo pipefail
AZ=/opt/homebrew/bin/az
KEY=/Users/gg1900/.ssh/id_ed25519.pub
RG=rg-waais-prod-weu  # validation only; doesn't actually deploy

regions=(
  westeurope northeurope
  francecentral germanywestcentral
  uksouth ukwest
  swedencentral switzerlandnorth
  norwayeast italynorth
  polandcentral spaincentral
)

skus=(
  Standard_B2s          # 2vCPU/4GB ~€26/mo  — original target
  Standard_B2as_v2      # 2vCPU/8GB ~€32/mo  — AMD burstable
  Standard_B2ms         # 2vCPU/8GB ~€51/mo  — older B
  Standard_DS1_v2       # 1vCPU/3.5GB ~€45/mo
)

OUT=/tmp/eu-probe.txt
: > "$OUT"

probe_one() {
  local sku="$1" region="$2" tmp
  tmp=$(mktemp)
  $AZ vm create -g "$RG" -n vm-validate-tmp -l "$region" \
    --image Ubuntu2204 --size "$sku" --admin-username waaisops \
    --ssh-key-values "$KEY" --public-ip-sku Standard \
    --public-ip-address-allocation static --nsg-rule SSH \
    --os-disk-size-gb 32 --storage-sku StandardSSD_LRS \
    --validate >"$tmp" 2>&1
  if grep -q "SkuNotAvailable" "$tmp"; then
    echo "RESTRICTED  $region  $sku" >> "$OUT"
  elif grep -q '"error": null' "$tmp"; then
    echo "AVAILABLE   $region  $sku" >> "$OUT"
  else
    echo "UNKNOWN     $region  $sku" >> "$OUT"
  fi
  rm -f "$tmp"
}

# Run all probes in parallel — 4 SKUs * 12 regions = 48 az calls
for sku in "${skus[@]}"; do
  for region in "${regions[@]}"; do
    probe_one "$sku" "$region" &
  done
done
wait
sort "$OUT" > "${OUT}.sorted"
mv "${OUT}.sorted" "$OUT"
echo "DONE" >> "$OUT"
