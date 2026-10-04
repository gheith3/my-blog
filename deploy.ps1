# deploy.ps1 — Tag the current commit as prod-<timestamp> to trigger the blog
# build/push workflow (.github/workflows/build-and-push-prod.yml), which pushes
# the image to GHCR and calls the Portainer webhook.
#
# Usage:
#   .\deploy.ps1
#

# Generate tag
$Timestamp = Get-Date -Format "yyyy-MM-dd_HHmm"
$Tag = "prod-$Timestamp"

# Confirm
Write-Host ""
Write-Host "  Environment: prod" -ForegroundColor Green
Write-Host "  Tag:         $Tag" -ForegroundColor Green
Write-Host ""

$confirm = Read-Host "Proceed? [Y/n]"
if ($confirm -match "^[Nn]") {
    Write-Host "Aborted." -ForegroundColor Red
    exit 0
}

# Execute
Write-Host "Creating tag $Tag..." -ForegroundColor Cyan
git tag $Tag

Write-Host "Pushing tag to origin..." -ForegroundColor Cyan
git push origin $Tag

Write-Host ""
Write-Host "Done! Tag $Tag pushed." -ForegroundColor Green
Write-Host "Check the build at: https://github.com/gheith3/my-blog/actions"
