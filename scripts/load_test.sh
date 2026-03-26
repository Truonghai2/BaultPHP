#!/bin/bash

# Load Testing Script for BaultFrame
# Requires: wrk (https://github.com/wg/wrk)

echo "🔥 BaultFrame Load Testing Suite"
echo "================================="
echo ""

# Configuration
HOST="http://localhost:8080"
DURATION="30s"
THREADS=4
CONNECTIONS=100

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test 1: Static Response
echo "📊 Test 1: Static Response (Health Check)"
echo "Target: >10,000 req/s"
wrk -t${THREADS} -c${CONNECTIONS} -d${DURATION} ${HOST}/health
echo ""

# Test 2: Database Query
echo "📊 Test 2: Database Query (List Users)"
echo "Target: >5,000 req/s"
wrk -t${THREADS} -c${CONNECTIONS} -d${DURATION} ${HOST}/api/users
echo ""

# Test 3: Cached Response
echo "📊 Test 3: Cached Response"
echo "Target: >15,000 req/s"
wrk -t${THREADS} -c${CONNECTIONS} -d${DURATION} ${HOST}/api/cached-data
echo ""

# Test 4: POST Request (CQRS Command)
echo "📊 Test 4: POST Request (Create Todo)"
echo "Target: >3,000 req/s"
wrk -t${THREADS} -c${CONNECTIONS} -d${DURATION} \
  -s scripts/wrk/post_todo.lua \
  ${HOST}/api/todos
echo ""

# Test 5: Concurrent Users Simulation
echo "📊 Test 5: High Concurrency (1000 concurrent)"
echo "Target: Stable response times"
wrk -t8 -c1000 -d${DURATION} ${HOST}/api/users
echo ""

echo "✅ Load Testing Complete!"
echo ""
echo "Next Steps:"
echo "1. Review results above"
echo "2. Compare with targets in PRODUCTION_READINESS_REPORT.md"
echo "3. Optimize bottlenecks if needed"
echo "4. Re-run tests after optimizations"
