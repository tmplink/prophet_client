#!/bin/bash

# Download
curl -o /usr/bin/prophet https://raw.githubusercontent.com/tmplink/prophet_client/main/prophet.php

# Kill
ps aux|grep prophet-main|grep -v grep|awk '{print $2}'|xargs kill -9

# Restart
prophet --restart