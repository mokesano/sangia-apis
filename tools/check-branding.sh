#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LEGACY_TERMS='wizdam'

if rg -n -i --hidden --glob '!.git' --glob '!tools/check-branding.sh' -e "$LEGACY_TERMS" .; then
  echo "❌ Legacy branding ditemukan. Ganti seluruh referensi nama lama menjadi Sangia."
  exit 1
fi

echo "✅ Tidak ada referensi legacy branding nama lama."
