# BaultFrame Optimization Guide

## 📖 Hướng Dẫn Tối Ưu Hóa Toàn Diện

Hướng dẫn này cung cấp best practices và workflows để tối ưu hóa BaultFrame application cho production.

---

## 📋 Mục Lục

1. [Quick Start](#quick-start)
2. [Available Commands](#available-commands)
3. [Production Deployment](#production-deployment)
4. [Optimization Workflows](#optimization-workflows)
5. [Monitoring & Maintenance](#monitoring--maintenance)
6. [Troubleshooting](#troubleshooting)
7. [Best Practices](#best-practices)

---

## 🚀 Quick Start

### Optimize Toàn Bộ Application (Recommended)

```bash
# Clear old caches trước
php bault cache:clear-all

# Optimize application
php bault optimize

# Verify kết quả
php bault performance:report
```

### Optimize Cho Development

```bash
# Chỉ optimize cơ bản, skip các bước nặng
php bault optimize --skip-warmup --skip-acl
```

### Optimize Cho Production

```bash
# Full optimization với tất cả các bước
php bault optimize

# Generate OPcache preload file
php bault optimize:preload

# Warm up caches
php bault cache:warmup --popular
```

---

## 📦 Available Commands

### Master Commands

#### `optimize` - Master Optimization Command

Optimize toàn bộ application cho production.

```bash
# Full optimization
php bault optimize

# Skip một số steps
php bault optimize --skip-blocks      # Skip block cache
php bault optimize --skip-acl         # Skip ACL optimization
php bault optimize --skip-jit         # Skip JIT optimization
php bault optimize --skip-warmup      # Skip cache warming
php bault optimize --no-autoload      # Skip autoloader optimization
```

**Các bước thực hiện:**
1. ✅ Cache framework files (config, routes, events, views, commands, bootstrap)
2. ✅ Cache blocks (CMS)
3. ✅ Optimize autoloader (composer dump-autoload)
4. ✅ Compile service container
5. ✅ JIT optimization
6. ✅ ACL cache warming
7. ✅ General cache warming

---

#### `cache:clear-all` - Clear All Caches

Clear tất cả caches một cách an toàn.

```bash
# With confirmation
php bault cache:clear-all

# Force clear without confirmation
php bault cache:clear-all --force
```

**Clears:**
- Configuration cache
- Route cache
- Event cache
- View cache
- Command cache
- Bootstrap cache
- Compiled container
- Block cache (CMS)
- Application cache
- OPcache
- Compiled views directory
- Cache directory

---

#### `cache:warmup` - Warm Up Caches

Preload caches để cải thiện performance.

```bash
# Warm up popular items (recommended)
php bault cache:warmup --popular

# Warm up all caches
php bault cache:warmup --all

# Warm up specific caches
php bault cache:warmup --blocks       # Block cache only
php bault cache:warmup --acl          # ACL cache only
php bault cache:warmup --routes       # Routes only
```

---

#### `performance:report` - Performance Report

Generate comprehensive performance report.

```bash
# Display report in terminal
php bault performance:report

# Detailed report
php bault performance:report --detailed

# JSON output (for monitoring tools)
php bault performance:report --json
```

**Metrics included:**
- ✅ OPcache performance (hit rate, memory usage, hot paths)
- ✅ Database performance (connection pool, query metrics, QPS)
- ✅ Cache configuration
- ✅ ACL performance (L1/L2 hit rates)
- ✅ System information
- ✅ Overall health score

---

### Cache Commands

#### Individual Cache Commands

```bash
# Configuration
php bault config:cache
php bault config:clear

# Routes
php bault route:cache
php bault route:clear

# Events
php bault event:cache
php bault event:clear

# Views
php bault view:cache
php bault view:clear

# Commands
php bault command:cache
php bault command:clear

# Bootstrap
php bault bootstrap:cache
php bault bootstrap:clear

# Blocks (CMS)
php bault cache:blocks
php bault cache:clear-blocks
php bault cache:stats-blocks
```

---

### JIT Optimization Commands

#### `optimize:jit` - JIT Optimization

Optimize PHP OPcache with JIT compilation.

```bash
# Run optimization
php bault optimize:jit optimize

# Show statistics
php bault optimize:jit stats

# Analyze and get recommendations
php bault optimize:jit analyze

# Reset optimization data
php bault optimize:jit reset
```

**Features:**
- Profile-based optimization
- Hot path detection
- Auto-optimization
- Memory management
- Hit rate optimization

---

#### `optimize:preload` - Generate OPcache Preload

Generate preload.php file cho OPcache.

```bash
# Generate with defaults
php bault optimize:preload

# Custom options
php bault optimize:preload --limit=1000 --min-hits=50

# Custom output path
php bault optimize:preload --output=/path/to/preload.php
```

**Usage:**
1. Generate preload file
2. Add to php.ini:
   ```ini
   opcache.preload=/path/to/preload.php
   opcache.preload_user=www-data
   ```
3. Restart PHP-FPM

---

### Service Container Commands

#### `optimize:compile` - Compile Container

Compile service container cho faster DI.

```bash
php bault optimize:compile
```

#### `optimize:clear` - Clear Compiled Container

```bash
php bault optimize:clear
```

---

### ACL Optimization Commands

#### `acl:optimize` - ACL Performance

Optimize ACL cache và view metrics.

```bash
# Warm cache
php bault acl:optimize warm

# Show metrics
php bault acl:optimize metrics

# Performance report
php bault acl:optimize report

# Reset metrics
php bault acl:optimize reset
```

#### `acl:schedule` - Scheduled Maintenance

```bash
# Warm active users (last 7 days)
php bault acl:schedule warm-active

# Warm all users
php bault acl:schedule warm-all

# Cleanup stale cache
php bault acl:schedule cleanup

# Log metrics
php bault acl:schedule metrics
```

---

### Database Commands

#### `db:analyze-pool` - Connection Pool Analysis

```bash
# Full analysis
php bault db:analyze-pool mysql

# Specific metrics
php bault db:analyze-pool mysql --metrics
php bault db:analyze-pool mysql --leaks
php bault db:analyze-pool mysql --recommendations
```

---

### Block Cache Commands (CMS)

#### `cache:warmup-blocks` - Warm Block Cache

```bash
# Popular pages
php bault cache:warmup-blocks --popular

# All pages
php bault cache:warmup-blocks --all

# Specific page
php bault cache:warmup-blocks --page=1
```

---

## 🚀 Production Deployment

### Complete Production Optimization Workflow

```bash
#!/bin/bash
# production-optimize.sh

echo "🚀 Starting production optimization..."

# Step 1: Clear all old caches
echo "Step 1: Clearing old caches..."
php bault cache:clear-all --force

# Step 2: Run full optimization
echo "Step 2: Running full optimization..."
php bault optimize

# Step 3: Generate OPcache preload
echo "Step 3: Generating OPcache preload..."
php bault optimize:preload --limit=1000

# Step 4: Warm up caches
echo "Step 4: Warming up caches..."
php bault cache:warmup --popular

# Step 5: Verify optimization
echo "Step 5: Verifying optimization..."
php bault performance:report

echo "✅ Production optimization completed!"
```

### Deployment Checklist

- [ ] Clear all caches: `php bault cache:clear-all --force`
- [ ] Run optimize: `php bault optimize`
- [ ] Generate preload: `php bault optimize:preload`
- [ ] Configure php.ini with preload path
- [ ] Restart PHP-FPM
- [ ] Warm up caches: `php bault cache:warmup --popular`
- [ ] Verify with: `php bault performance:report`
- [ ] Monitor logs for errors
- [ ] Check OPcache stats: `php bault optimize:jit stats`
- [ ] Check database pool: `php bault db:analyze-pool mysql`

---

## 🔄 Optimization Workflows

### Daily Optimization (Automated via Cron)

```bash
# /etc/cron.daily/baultframe-optimize
0 2 * * * cd /var/www/baultframe && php bault cache:warmup --popular
0 3 * * * cd /var/www/baultframe && php bault acl:schedule warm-active
0 4 * * * cd /var/www/baultframe && php bault acl:schedule cleanup
```

### Weekly Optimization

```bash
# /etc/cron.weekly/baultframe-optimize
0 1 * * 0 cd /var/www/baultframe && php bault optimize --skip-warmup
0 2 * * 0 cd /var/www/baultframe && php bault acl:schedule warm-all
```

### After Code Update

```bash
# Clear caches
php bault cache:clear-all --force

# Re-optimize
php bault optimize

# Regenerate preload if using it
php bault optimize:preload

# Restart services
sudo systemctl restart php-fpm
```

### Performance Troubleshooting

```bash
# 1. Check overall health
php bault performance:report

# 2. Check OPcache
php bault optimize:jit stats
php bault optimize:jit analyze

# 3. Check database
php bault db:analyze-pool mysql --recommendations

# 4. Check ACL
php bault acl:optimize report

# 5. Check block cache
php bault cache:stats-blocks
```

---

## 📊 Monitoring & Maintenance

### Health Check Script

```bash
#!/bin/bash
# health-check.sh

# Generate report and save to file
php bault performance:report --json > /tmp/performance-report.json

# Parse health score
HEALTH_SCORE=$(cat /tmp/performance-report.json | jq '.overall_health.score')

# Alert if health is poor
if (( $(echo "$HEALTH_SCORE < 75" | bc -l) )); then
    echo "⚠️ Health score is low: $HEALTH_SCORE"
    # Send alert (email, Slack, etc.)
fi
```

### Monitoring Metrics

**OPcache:**
```bash
php bault optimize:jit stats
# Target: Hit rate > 95%, Memory usage < 75%
```

**Database:**
```bash
php bault db:analyze-pool mysql
# Target: Utilization < 75%, QPS stable, No leaks
```

**ACL:**
```bash
php bault acl:optimize metrics
# Target: Hit rate > 90%, L1 hits > L2 hits
```

**Overall:**
```bash
php bault performance:report
# Target: Health score > 80
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Low OPcache Hit Rate

**Problem:** Hit rate < 85%

**Solutions:**
```bash
# Analyze the issue
php bault optimize:jit analyze

# Run optimization
php bault optimize:jit optimize

# Increase memory if needed (php.ini)
opcache.memory_consumption=256

# Restart PHP-FPM
sudo systemctl restart php-fpm
```

---

#### 2. High Database Pool Utilization

**Problem:** Utilization > 85%

**Solutions:**
```bash
# Analyze pool
php bault db:analyze-pool mysql --recommendations

# Increase pool size (config/database.php)
'pool_size' => 50, // Increase from default

# Enable adaptive pooling (config/database-optimization.php)
'adaptive_pool' => [
    'enabled' => true,
    'max_pool_size' => 100,
],
```

---

#### 3. Cache Misses

**Problem:** High cache miss rate

**Solutions:**
```bash
# Warm up caches
php bault cache:warmup --all

# For ACL specifically
php bault acl:optimize warm

# For blocks
php bault cache:warmup-blocks --all
```

---

#### 4. Memory Issues

**Problem:** High memory usage

**Solutions:**
```bash
# Check memory usage
php bault performance:report --detailed

# Clear unnecessary caches
php bault cache:clear-all

# Reduce preload file size
php bault optimize:preload --limit=200

# Adjust php.ini
memory_limit=512M
opcache.memory_consumption=128
```

---

#### 5. Slow Query Performance

**Problem:** High query latency

**Solutions:**
```bash
# Analyze queries
php bault db:analyze-pool mysql --metrics

# Check for slow queries
# Review output for P95/P99 latencies

# Optimize indexes
# Review slow query log
# Add indexes to frequently queried columns
```

---

## 💡 Best Practices

### Development Environment

```bash
# Minimal optimization for faster development
php bault optimize --skip-warmup --skip-acl --skip-jit

# Or just cache configs and routes
php bault config:cache
php bault route:cache
```

### Staging Environment

```bash
# Full optimization without preload
php bault cache:clear-all
php bault optimize
php bault cache:warmup --popular
```

### Production Environment

```bash
# Full optimization with preload
php bault cache:clear-all --force
php bault optimize
php bault optimize:preload
php bault cache:warmup --popular

# Configure php.ini
opcache.preload=/path/to/preload.php
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.enable_file_override=1

# Restart PHP-FPM
sudo systemctl restart php-fpm
```

### OPcache Configuration

**Recommended php.ini settings:**

```ini
; OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; Production only
opcache.save_comments=1
opcache.enable_file_override=1

; JIT (PHP 8.0+)
opcache.jit=1255
opcache.jit_buffer_size=128M

; Preload (PHP 7.4+)
opcache.preload=/path/to/bootstrap/cache/preload.php
opcache.preload_user=www-data
```

### Cache Strategy

**Multi-tier caching:**
1. **L1 (APCu)** - Fast in-memory cache for hot data
2. **L2 (Redis)** - Shared cache across servers
3. **L3 (Database)** - Persistent storage

**Cache warming strategy:**
- Warm popular items after deployment
- Schedule daily warming for active users
- Warm specific items on-demand

### Monitoring Strategy

**Automated monitoring:**
```bash
# Every 5 minutes
*/5 * * * * /path/to/health-check.sh

# Daily report
0 9 * * * cd /var/www/baultframe && php bault performance:report --json > /var/log/performance-$(date +\%Y\%m\%d).json
```

**Alerting thresholds:**
- OPcache hit rate < 90%
- Database utilization > 85%
- ACL hit rate < 85%
- Overall health score < 75

---

## 📈 Performance Metrics

### Target Metrics

| Metric | Target | Warning | Critical |
|--------|--------|---------|----------|
| OPcache Hit Rate | > 95% | < 90% | < 85% |
| OPcache Memory | < 75% | > 80% | > 90% |
| DB Pool Utilization | < 75% | > 80% | > 90% |
| Query Success Rate | > 99% | < 99% | < 95% |
| ACL Hit Rate | > 90% | < 85% | < 75% |
| Overall Health Score | > 85 | < 80 | < 70 |

### Benchmark Commands

```bash
# Run performance benchmark
php bault performance:test

# Measure optimization impact
php bault performance:report --json > before.json
php bault optimize
php bault performance:report --json > after.json

# Compare results
diff <(jq . before.json) <(jq . after.json)
```

---

## 🎯 Summary

### Quick Reference

```bash
# Daily routine
php bault cache:warmup --popular

# After deployment
php bault cache:clear-all --force && php bault optimize

# Health check
php bault performance:report

# Troubleshooting
php bault optimize:jit analyze
php bault db:analyze-pool mysql
php bault acl:optimize report
```

### Command Cheat Sheet

| Task | Command |
|------|---------|
| Full optimization | `php bault optimize` |
| Clear all caches | `php bault cache:clear-all` |
| Warm up caches | `php bault cache:warmup --popular` |
| Health report | `php bault performance:report` |
| JIT stats | `php bault optimize:jit stats` |
| Generate preload | `php bault optimize:preload` |
| Database analysis | `php bault db:analyze-pool mysql` |
| ACL optimization | `php bault acl:optimize warm` |

---

## 📚 Additional Resources

- [Performance Innovations Guide](./PERFORMANCE_INNOVATIONS_GUIDE.md)
- [Advanced Caching Guide](./ADVANCED_CACHING_GUIDE.md)
- [Database Optimization Guide](./ADVANCED_DATABASES_GUIDE.md)
- [Swoole Enhancements](./SWOOLE_ENHANCEMENTS_SUMMARY.md)
- [Modern PHP Features](./MODERN_PHP_FEATURES_GUIDE.md)

---

**Last Updated:** 2026-01-22
**Version:** 3.0
