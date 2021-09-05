#!/bin/bash
#
# Copy config files to ram tmpfs.
#
/usr/bin/rm /tmp/snapshots/config/*.cfg
/usr/bin/cp /boot/config/plugins/snapshots/*.cfg /tmp/snapshots/config/ 2>/dev/null
