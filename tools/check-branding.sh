#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LEGACY_TERMS='w[iI]zdam'

if rg -n --hidden --glob '!.git' -e "$LEGACY_TERMS" .; then
  echo "❌ Legacy branding ditemukan. Ganti seluruh referensi nama lama menjadi Sangia."
  exit 1
fi

echo "✅ Tidak ada referensi legacy branding nama lama."
