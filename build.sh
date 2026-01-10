#!/bin/bash
# Build script for creating a distributable Stripe Auditor plugin package

set -e

PLUGIN_SLUG="stripe-auditor"
BUILD_DIR="build"
DIST_DIR="dist"

echo "🔧 Building $PLUGIN_SLUG plugin..."

# Clean previous builds
echo "🧹 Cleaning previous builds..."
rm -rf "$BUILD_DIR" "$DIST_DIR"

# Create build directory
echo "📁 Creating build directory..."
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# Install production Composer dependencies first
echo "📦 Installing production dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Copy plugin files (excluding dev files)
echo "📋 Copying plugin files..."
rsync -rc \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude=".idea" \
  --exclude="REFACTORING.md" \
  --exclude="docs" \
  --exclude="$BUILD_DIR" \
  --exclude="$DIST_DIR" \
  --exclude="build.sh" \
  --exclude="test_*.php" \
  --exclude="reproduce_error.php" \
  --exclude="vendor/**/tests/" \
  --exclude="vendor/**/test/" \
  --exclude="vendor/**/docs/" \
  --exclude="vendor/**/doc/" \
  --exclude="vendor/**/.github/" \
  ./ "$BUILD_DIR/$PLUGIN_SLUG/"

# Create dist directory and zip file
echo "🗜️  Creating ZIP archive..."
mkdir -p "$DIST_DIR"
cd "$BUILD_DIR"
zip -r "../$DIST_DIR/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG" -q
cd ..

# Clean up build directory
echo "🧹 Cleaning up..."
rm -rf "$BUILD_DIR"

echo "✅ Build complete! Package created at: $DIST_DIR/$PLUGIN_SLUG.zip"
echo "📦 Your collaborator can now download and install this zip file."
