# Release Checklist

Use this checklist for every public release.

## 1) Prepare

- Pull latest changes on main.
- Decide version number (for example: v1.0.3).
- Update CHANGELOG.md with the new version and date.
- Update composer.json constraints or metadata if needed.
- Update README.md if behavior or support changed.

## 2) Validate

- Run Composer validation:

```bash
composer validate --no-check-publish
```

- Run package tests if your project has them.

## 3) Commit Release Prep

```bash
git checkout main
git pull origin main
git add CHANGELOG.md composer.json README.md
git commit -m "chore(release): prepare vX.Y.Z"
```

## 4) Tag and Push

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin main
git push origin --tags
```

## 5) Publish GitHub Release

```bash
gh auth status
gh release create vX.Y.Z --title "vX.Y.Z" --notes-file CHANGELOG.md
```

If the release already exists, view it:

```bash
gh release view vX.Y.Z
```

## 6) Verify Package Distribution

- Confirm release appears in GitHub Releases.
- Confirm Packagist has indexed the new tag.
- If Packagist did not auto-refresh, trigger an update from Packagist UI.

## 7) Optional Maintenance Branch

For long-running major line support, keep a major branch such as 1.x:

```bash
git branch --list 1.x
git push origin 1.x
```

## Summary

```bash
git checkout main
git pull origin main
composer validate --no-check-publish
git add CHANGELOG.md composer.json README.md
git commit -m "chore(release): prepare vX.Y.Z"
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin main
git push origin --tags
gh release create vX.Y.Z --title "vX.Y.Z" --notes-file CHANGELOG.md
```
