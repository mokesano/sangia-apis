#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LEGACY_TERMS='wizdam'

if rg -n -i --hidden --glob '!.git' --glob '!tools/check-branding.sh' -e "$LEGACY_TERMS" .; then
  echo "❌ Legacy branding ditemukan di isi file. Ganti seluruh referensi nama lama menjadi Sangia."
  exit 1
fi

legacy_files=$(rg --files --hidden --glob '!.git' | awk 'BEGIN{IGNORECASE=1} /wizdam/ {print}')
if [[ -n "$legacy_files" ]]; then
  echo "$legacy_files"
  echo "❌ Legacy branding ditemukan di nama file. Ganti nama file menjadi Sangia."
  exit 1
fi

echo "✅ Tidak ada referensi legacy branding nama lama."
