#!/bin/bash
while true; do
  git add .
  git commit -m "auto-commit: $(date +"%Y-%m-%d %H:%M:%S")"
  git push origin main
  sleep 60
done
