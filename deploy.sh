#!/usr/bin/env bash
#
# deploy.sh — Create a git tag that triggers the blog build/push workflow
# (.github/workflows/build-and-push-prod.yml / build-and-push-test.yml).
#
# Usage:
#   ./deploy.sh [environment]
#
# Examples:
#   ./deploy.sh test
#   ./deploy.sh prod
#   ./deploy.sh                          # prompts interactively
#
# Valid environments: test, prod
#
set -euo pipefail

# ── Colors ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ── Validation ─────────────────────────────────────────────────────────────
VALID_ENVS=("test" "prod")

usage() {
    echo -e "${CYAN}Usage:${NC} $0 [environment]"
    echo ""
    echo -e "  ${CYAN}environment${NC}  test | prod"
    echo ""
    echo -e "Examples:"
    echo -e "  $0 test"
    echo -e "  $0 prod"
    exit 1
}

contains() {
    local needle="$1"
    shift
    for item in "$@"; do
        [[ "$item" == "$needle" ]] && return 0
    done
    return 1
}

# ── Parse arguments ────────────────────────────────────────────────────────
ENV="${1:-}"

# Interactive prompt if not provided
if [[ -z "$ENV" ]]; then
    echo -e "${CYAN}Select environment:${NC}"
    select env_opt in "test" "prod"; do
        [[ -n "$env_opt" ]] && ENV="$env_opt" && break
        echo -e "${RED}Invalid choice.${NC}"
    done
fi

# Validate
if ! contains "$ENV" "${VALID_ENVS[@]}"; then
    echo -e "${RED}Error:${NC} Invalid environment '${ENV}'. Valid: ${VALID_ENVS[*]}"
    exit 1
fi

# ── Determine tag prefix ───────────────────────────────────────────────────
PREFIX="$ENV"

# ── Generate tag name ─────────────────────────────────────────────────────
TIMESTAMP=$(date +%Y-%m-%d_%H%M)
TAG="${PREFIX}-${TIMESTAMP}"

# ── Confirm ────────────────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}┌─────────────────────────────────────────┐${NC}"
echo -e "${YELLOW}│  Environment:${NC} ${GREEN}${ENV}${NC}"
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
