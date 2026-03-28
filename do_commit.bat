@echo off
git add .
git commit -m "Update BaultPHP framework core and infrastructure"
git tag v1.0.4
echo SUCCESS > commit_success.txt
