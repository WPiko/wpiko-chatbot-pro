#!/bin/bash

# WPiko Chatbot Pro Release Script
# This script helps automate the release process for GitHub updates

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_FILE="wpiko-chatbot-pro.php"
CONFIG_FILE="includes/github-config.php"

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

# Function to check if we're in a git repository
check_git_repo() {
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        print_error "Not in a git repository. Please run this script from your plugin's git repository."
        exit 1
    fi
}

# Function to check if working directory is clean
check_clean_working_dir() {
    if [[ -n $(git status --porcelain) ]]; then
        print_warning "Working directory is not clean. You have uncommitted changes:"
        git status --short
        echo
        read -p "Do you want to continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# Function to get current version from plugin file
get_current_version() {
    if [[ ! -f "$PLUGIN_FILE" ]]; then
        print_error "Plugin file '$PLUGIN_FILE' not found!"
        exit 1
    fi
    
    grep -o "Version: [0-9\.]*" "$PLUGIN_FILE" | cut -d' ' -f2
}

# Function to get current version from constant
get_constant_version() {
    grep -o "WPIKO_CHATBOT_PRO_VERSION', '[0-9\.]*'" "$PLUGIN_FILE" | cut -d"'" -f2
}

# Function to update version in plugin file
update_plugin_version() {
    local new_version=$1
    
    # Update plugin header version
    sed -i.bak "s/Version: [0-9\.]*/Version: $new_version/" "$PLUGIN_FILE"
    
    # Update constant version
    sed -i.bak "s/WPIKO_CHATBOT_PRO_VERSION', '[0-9\.]*'/WPIKO_CHATBOT_PRO_VERSION', '$new_version'/" "$PLUGIN_FILE"
    
    # Remove backup file
    rm "${PLUGIN_FILE}.bak"
    
    print_success "Updated version to $new_version in $PLUGIN_FILE"
}

# Function to validate version format
validate_version() {
    local version=$1
    if [[ ! $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        print_error "Version must be in format X.Y.Z (e.g., 1.2.0)"
        return 1
    fi
    return 0
}

# Function to check if GitHub config is set up
check_github_config() {
    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "GitHub config file '$CONFIG_FILE' not found!"
        exit 1
    fi
    
    if grep -q "your-github-username" "$CONFIG_FILE" || grep -q "your-github-personal-access-token" "$CONFIG_FILE"; then
        print_error "GitHub configuration not set up properly!"
        print_error "Please update $CONFIG_FILE with your actual GitHub settings."
        exit 1
    fi
    
    print_success "GitHub configuration looks good"
}

# Function to create and push tag
create_and_push_tag() {
    local version=$1
    local tag="v$version"
    
    # Check if tag already exists
    if git tag -l | grep -q "^$tag$"; then
        print_error "Tag '$tag' already exists!"
        exit 1
    fi
    
    # Create tag
    git tag -a "$tag" -m "Release version $version"
    print_success "Created tag '$tag'"
    
    # Push tag
    git push origin "$tag"
    print_success "Pushed tag '$tag' to origin"
}

# Function to commit version changes
commit_version_changes() {
    local version=$1
    
    git add "$PLUGIN_FILE"
    git commit -m "Bump version to $version"
    print_success "Committed version changes"
    
    git push origin $(git branch --show-current)
    print_success "Pushed changes to origin"
}

# Function to generate changelog template
generate_changelog_template() {
    local version=$1
    cat << EOF

## Release Notes Template for Version $version

Copy this template for your GitHub release description:

---

## What's New in Version $version

### New Features
* 

### Improvements
* 

### Bug Fixes
* 

### Technical Changes
* 

---

EOF
}

# Main script
main() {
    print_status "WPiko Chatbot Pro Release Script"
    echo "=================================="
    echo
    
    # Check prerequisites
    check_git_repo
    check_github_config
    check_clean_working_dir
    
    # Get current version
    current_version=$(get_current_version)
    constant_version=$(get_constant_version)
    
    if [[ "$current_version" != "$constant_version" ]]; then
        print_warning "Version mismatch detected:"
        print_warning "  Plugin header: $current_version"
        print_warning "  Constant: $constant_version"
    fi
    
    print_status "Current version: $current_version"
    echo
    
    # Get new version
    read -p "Enter new version (e.g., 1.2.0): " new_version
    
    # Validate version
    if ! validate_version "$new_version"; then
        exit 1
    fi
    
    # Check if version is newer
    if [[ "$new_version" == "$current_version" ]]; then
        print_error "New version must be different from current version"
        exit 1
    fi
    
    # Summary
    echo
    print_status "Release Summary:"
    echo "  Current version: $current_version"
    echo "  New version: $new_version"
    echo "  Tag: v$new_version"
    echo
    
    read -p "Proceed with release? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Release cancelled"
        exit 0
    fi
    
    echo
    print_status "Starting release process..."
    
    # Update version
    update_plugin_version "$new_version"
    
    # Commit changes
    commit_version_changes "$new_version"
    
    # Create and push tag
    create_and_push_tag "$new_version"
    
    # Generate changelog template
    generate_changelog_template "$new_version"
    
    print_success "Release process completed!"
    echo
    print_status "Next steps:"
    echo "1. Go to your GitHub repository"
    echo "2. Navigate to Releases"
    echo "3. Click 'Create a new release'"
    echo "4. Select tag 'v$new_version'"
    echo "5. Add release notes using the template above"
    echo "6. Publish the release"
    echo
    print_status "Your WordPress sites will automatically detect the new version within 12 hours"
    print_status "Or users can force an update check from the License Activation page"
}

# Run the script
main "$@"
