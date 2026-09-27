#!/bin/sh
set -e
curl -fsS http://localhost/api/health
echo
curl -fsS http://localhost/api/dashboard
echo
