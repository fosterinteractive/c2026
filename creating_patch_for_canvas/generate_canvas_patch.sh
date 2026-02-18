#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_CANVAS="$SCRIPT_DIR/source/canvas"
CLONED_CANVAS="$SCRIPT_DIR/clone_here/canvas"
PATCH_NAME="issues-3549232-3533079-3545816-3558241-3548718-3551315-3569120-3571988-3541873.patch"
PATCH_FILE="$SCRIPT_DIR/$PATCH_NAME"

# 1. Check source/canvas.
if [[ ! -d "$SOURCE_CANVAS" ]]; then
  echo "ERROR: Canvas folder not found in source directory: $SOURCE_CANVAS"
  exit 1
fi

# 2. Check clone_here/canvas.
if [[ ! -d "$CLONED_CANVAS" ]]; then
  echo "ERROR: Canvas folder not found in clone_here directory."
  echo "Please clone the Canvas module from its repo and place it here: $CLONED_CANVAS"
  exit 1
fi

# Ensure cloned folder is a git repository.
if [[ ! -d "$CLONED_CANVAS/.git" ]]; then
  echo "ERROR: clone_here/canvas exists, but it is not a git repository: $CLONED_CANVAS"
  exit 1
fi

# 3. Replace required files/folders.
SOURCE_MODULE_DIR="$SOURCE_CANVAS/modules/canvas_ai"
CLONED_MODULE_DIR="$CLONED_CANVAS/modules/canvas_ai"

SOURCE_AIWIZARD="$SOURCE_CANVAS/ui/src/components/aiExtension/AiWizard.tsx"
CLONED_AIWIZARD="$CLONED_CANVAS/ui/src/components/aiExtension/AiWizard.tsx"

SOURCE_COMPONENT_DIR="$SOURCE_CANVAS/src/Component"
CLONED_COMPONENT_DIR="$CLONED_CANVAS/src/Component"
CLONED_SERVICES_FILE="$CLONED_CANVAS/canvas.services.yml"

if [[ ! -d "$SOURCE_MODULE_DIR" ]]; then
  echo "ERROR: Source canvas_ai folder not found at modules/canvas_ai in $SOURCE_CANVAS"
  exit 1
fi

if [[ ! -f "$SOURCE_AIWIZARD" ]]; then
  echo "ERROR: Source file not found: $SOURCE_AIWIZARD"
  exit 1
fi

if [[ ! -d "$SOURCE_COMPONENT_DIR" ]]; then
  echo "ERROR: Source folder not found: $SOURCE_COMPONENT_DIR"
  exit 1
fi

if [[ ! -f "$CLONED_SERVICES_FILE" ]]; then
  echo "ERROR: Destination services file not found: $CLONED_SERVICES_FILE"
  exit 1
fi

echo "Applying replacements..."

# Replace the canvas_ai folder.
rm -rf "$CLONED_MODULE_DIR"
cp -r "$SOURCE_MODULE_DIR" "$CLONED_MODULE_DIR"
echo "  Replaced modules/canvas_ai"

# Replace AIWizard.
cp -f "$SOURCE_AIWIZARD" "$CLONED_AIWIZARD"
echo "  Replaced ui/src/components/aiExtension/AiWizard.tsx"

rm -rf "$CLONED_COMPONENT_DIR"
mkdir -p "$(dirname "$CLONED_COMPONENT_DIR")"
cp -r "$SOURCE_COMPONENT_DIR" "$(dirname "$CLONED_COMPONENT_DIR")/"
echo "  Replaced src/Component"

# Replace canvas.services.yml service definitions.
cat >> "$CLONED_SERVICES_FILE" << 'EOF'
  Drupal\canvas\Component\Schema\PropChoiceOptionsResolver:
    arguments:
      $stringTranslation: '@string_translation'
  Drupal\canvas\Component\Schema\PropMetadataNormalizer: {}
EOF
echo "  Updated canvas.services.yml"

echo "Generating patch..."
cd "$CLONED_CANVAS"
git add -A
git diff --cached > "$PATCH_FILE"
# Unstage so the destination repo is left clean
git reset HEAD

echo "Patch generated. Apply it via composer"
