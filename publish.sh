#!/bin/bash

#
# OptiPress Plugin Packaging Utility
#
# Usage: ./package.sh [version]
# Example: ./package.sh 0.2.9
#
# This script:
# 1. Updates version numbers in plugin files
# 2. Creates a clean distribution package in ./dist
# 3. Excludes development files as defined in .distignore
#

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [version]"
    echo ""
    echo "Examples:"
    echo "  $0 0.2.9"
    echo "  $0 1.0.0"
    echo ""
    echo "The script will:"
    echo "  1. Update version in optipress.php header"
    echo "  2. Update OPTIPRESS_VERSION constant"
    echo "  3. Update version in package.json"
    echo "  4. Update Stable tag in readme.txt (WordPress.org requirement)"
    echo "  5. Create clean distribution package"
    echo "  6. Generate OptiPress-[version].zip in ./dist"
}

# Validate inputs
if [ $# -eq 0 ]; then
    print_error "Version number is required"
    show_usage
    exit 1
fi

VERSION="$1"

# Validate version format (basic check for semantic versioning)
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    print_warning "Version should follow semantic versioning (e.g., 1.0.0)"
    echo -n "Continue anyway? (y/N): "
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Check if we're in the right directory
if [ ! -f "optipress.php" ]; then
    print_error "optipress.php not found. Please run this script from the plugin root directory."
    exit 1
fi

if [ ! -f ".distignore" ]; then
    print_error ".distignore not found. Please run this script from the plugin root directory."
    exit 1
fi

print_status "Starting packaging process for OptiPress v$VERSION"

# Step 1: Update version in plugin header
print_status "Updating plugin header version..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/^ \* Version:.*/ * Version:     $VERSION/" optipress.php
else
    # Linux
    sed -i "s/^ \* Version:.*/ * Version:     $VERSION/" optipress.php
fi

# Step 2: Update version constant
print_status "Updating version constant..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/define( 'OPTIPRESS_VERSION', '.*' );/define( 'OPTIPRESS_VERSION', '$VERSION' );/" optipress.php
else
    # Linux
    sed -i "s/define( 'OPTIPRESS_VERSION', '.*' );/define( 'OPTIPRESS_VERSION', '$VERSION' );/" optipress.php
fi

# Step 2.5: Update package.json version
if [ -f "package.json" ]; then
    print_status "Updating package.json version..."
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" package.json
    else
        # Linux
        sed -i "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" package.json
    fi
fi

# Step 2.6: Update readme.txt Stable tag (WordPress.org requirement)
if [ -f "readme.txt" ]; then
    print_status "Updating readme.txt Stable tag..."
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/^Stable tag: .*/Stable tag: $VERSION/" readme.txt
    else
        # Linux
        sed -i "s/^Stable tag: .*/Stable tag: $VERSION/" readme.txt
    fi
fi

print_success "Version updated to $VERSION in optipress.php, package.json, and readme.txt"

# Step 3: Verify updates
print_status "Verifying version updates..."
HEADER_VERSION=$(grep "^ \* Version:" optipress.php | sed 's/.*Version:[[:space:]]*//')
CONSTANT_VERSION=$(grep "OPTIPRESS_VERSION" optipress.php | sed "s/.*'\(.*\)'.*/\1/")

if [ "$HEADER_VERSION" != "$VERSION" ]; then
    print_error "Header version update failed. Expected: $VERSION, Got: $HEADER_VERSION"
    exit 1
fi

if [ "$CONSTANT_VERSION" != "$VERSION" ]; then
    print_error "Constant version update failed. Expected: $VERSION, Got: $CONSTANT_VERSION"
    exit 1
fi

# Verify package.json if it exists
if [ -f "package.json" ]; then
    PACKAGE_VERSION=$(grep '"version":' package.json | sed 's/.*"version": "\(.*\)".*/\1/')
    if [ "$PACKAGE_VERSION" != "$VERSION" ]; then
        print_error "package.json version update failed. Expected: $VERSION, Got: $PACKAGE_VERSION"
        exit 1
    fi
fi

# Verify readme.txt if it exists
if [ -f "readme.txt" ]; then
    README_STABLE_TAG=$(grep "^Stable tag:" readme.txt | sed 's/Stable tag: //')
    if [ "$README_STABLE_TAG" != "$VERSION" ]; then
        print_error "readme.txt Stable tag update failed. Expected: $VERSION, Got: $README_STABLE_TAG"
        exit 1
    fi
fi

print_success "Version verification passed"

# Step 4: Clean and create dist directory
print_status "Preparing distribution directory..."
rm -rf dist/
mkdir -p dist/optipress

# Step 5: Copy files excluding development files
print_status "Copying files (excluding development files)..."
if command -v rsync >/dev/null 2>&1; then
    rsync -av --exclude-from='.distignore' --exclude='.*' . dist/optipress/
else
    print_warning "rsync not found, using cp (may include some dev files)"
    cp -r . dist/optipress/
    # Basic cleanup if rsync is not available
    rm -rf dist/optipress/.git
    rm -rf dist/optipress/.github
    rm -rf dist/optipress/.claude
    rm -rf dist/optipress/.vscode
    rm -rf dist/optipress/.idea
    rm -f dist/optipress/.*
    rm -rf dist/optipress/node_modules
    rm -rf dist/optipress/tests
fi

# Step 6: Create ZIP package
print_status "Creating ZIP package..."
cd dist
zip -r "OptiPress-$VERSION.zip" optipress/ >/dev/null

# Step 7: Verify package
PACKAGE_SIZE=$(du -h "OptiPress-$VERSION.zip" | cut -f1)
FILE_COUNT=$(unzip -l "OptiPress-$VERSION.zip" | tail -1 | awk '{print $2}')

print_success "Package created: OptiPress-$VERSION.zip"
print_status "Package size: $PACKAGE_SIZE"
print_status "Files included: $FILE_COUNT"

# Step 8: Verify version in package
print_status "Verifying packaged version..."
PACKAGED_VERSION=$(unzip -p "OptiPress-$VERSION.zip" optipress/optipress.php | grep "OPTIPRESS_VERSION" | sed "s/.*'\(.*\)'.*/\1/")

if [ "$PACKAGED_VERSION" != "$VERSION" ]; then
    print_error "Packaged version mismatch. Expected: $VERSION, Got: $PACKAGED_VERSION"
    exit 1
fi

cd ..

print_success "✅ Packaging complete!"
echo ""
echo "📦 Package: ./dist/OptiPress-$VERSION.zip"
echo "📁 Source:  ./dist/optipress/"
echo "🔢 Version: $VERSION"
echo "📊 Size:    $PACKAGE_SIZE"
echo ""
print_status "Ready for distribution!"

# Optional: Show package contents
echo -n "Show package contents? (y/N): "
read -r response
if [[ "$response" =~ ^[Yy]$ ]]; then
    echo ""
    print_status "Package contents:"
    unzip -l "dist/OptiPress-$VERSION.zip" | head -20
    if [ "$FILE_COUNT" -gt 20 ]; then
        echo "... and $(($FILE_COUNT - 20)) more files"
    fi
fi