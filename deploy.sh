#!/usr/bin/env bash
#
# deploy.sh — Tag the current commit as prod-<timestamp> to trigger the blog
# build/push workflow (.github/workflows/build-and-push-prod.yml), which pushes
# the image to GHCR and calls the Portainer webhook.
#
# Usage:
#   ./deploy.sh
#
set -euo pipefail

# ── Colors ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ── Generate tag name ─────────────────────────────────────────────────────
TIMESTAMP=$(date +%Y-%m-%d_%H%M)
TAG="prod-${TIMESTAMP}"

# ── Confirm ────────────────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}┌─────────────────────────────────────────┐${NC}"
echo -e "${YELLOW}│  Environment:${NC} ${GREEN}prod${NC}"
echo -e "${YELLOW}│  Tag:        ${NC} ${GREEN}${TAG}${NC}"
echo -e "${YELLOW}└─────────────────────────────────────────┘${NC}"
echo ""

read -rp "Proceed? [Y/n] " confirm
if [[ "$confirm" =~ ^[Nn] ]]; then
    echo -e "${RED}Aborted.${NC}"
    exit 0
fi

# ── Execute ────────────────────────────────────────────────────────────────
echo -e "${CYAN}Creating tag ${TAG}...${NC}"
git tag "$TAG"

echo -e "${CYAN}Pushing tag to origin...${NC}"
git push origin "$TAG"

echo ""
echo -e "${GREEN}Done!${NC} Tag ${TAG} pushed."
echo -e "Check the build at: https://github.com/gheith3/my-blog/actions"
