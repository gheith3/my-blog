# deploy.ps1 — Create a git tag that triggers the blog build/push workflow
# (.github/workflows/build-and-push-prod.yml / build-and-push-test.yml).
#
# Usage:
#   .\deploy.ps1 -Env test
#   .\deploy.ps1 -Env prod
#   .\deploy.ps1                    # prompts interactively
#
param(
    [Parameter(Position=0)]
    [ValidateSet("test", "prod", "", IgnoreCase = $true)]
    [string]$Env = ""
)

$ValidEnvs = @("test", "prod")

# ValidateSet accepts "-Env Prod" case-insensitively but preserves the
# literal casing typed — the git tag it builds must be lowercase to match
# the workflows' `tags: [prod-*]` / `tags: [test-*]` triggers, which are
# case-sensitive (a tag like "Prod-..." silently triggers nothing).
if (-not [string]::IsNullOrEmpty($Env)) {
    $Env = $Env.ToLowerInvariant()
}

# Interactive prompt if not provided
if ([string]::IsNullOrEmpty($Env)) {
    Write-Host "Select environment:" -ForegroundColor Cyan
    for ($i = 0; $i -lt $ValidEnvs.Count; $i++) {
        Write-Host "  $($i + 1). $($ValidEnvs[$i])"
    }
    do {
        $choice = Read-Host "Enter number (1-$($ValidEnvs.Count))"
    } until ($choice -match '^\d+$' -and [int]$choice -ge 1 -and [int]$choice -le $ValidEnvs.Count)
    $Env = $ValidEnvs[[int]$choice - 1]
}

# Determine prefix
$Prefix = $Env

# Generate tag
$Timestamp = Get-Date -Format "yyyy-MM-dd_HHmm"
$Tag = "$Prefix-$Timestamp"

# Confirm
Write-Host ""
Write-Host "  Environment: $Env" -ForegroundColor Green
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
