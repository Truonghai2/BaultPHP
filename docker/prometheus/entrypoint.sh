#!/bin/sh
set -e

# Check if PROMETHEUS_METRICS_TOKEN is set
if [ -z "$PROMETHEUS_METRICS_TOKEN" ]; then
  echo "Error: PROMETHEUS_METRICS_TOKEN environment variable is not set."
  exit 1
fi

# Substitute the token in the template file and output to the final config file
sed "s|\${PROMETHEUS_METRICS_TOKEN}|$PROMETHEUS_METRICS_TOKEN|g" /etc/prometheus/prometheus.yml.template > /etc/prometheus/prometheus.yml

# Execute the original prometheus command
/bin/prometheus --config.file=/etc/prometheus/prometheus.yml