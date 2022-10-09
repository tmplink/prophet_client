#!/bin/bash

set -e
### -----------colors for output---------------------
FRED="\033[31m" # foreground red
FGRN="\033[32m" # foreground green

### -----------stop if there is no privileges---------------------
if (($EUID != 0)); then
    echo -e "$FRED Please run with root"
    exit
fi

### -----------to get key value from shell params ---------------------
usage() {
    echo "Usage: $0 [-k <string>]" 1>&2
    exit 1
}
while getopts ":k:" o; do
    case "${o}" in
    k)
        k=${OPTARG}
        ;;
    *)
        usage
        ;;
    esac
done
shift $((OPTIND - 1))

if [ -z "${k}" ]; then
    usage
fi

### -----------to tell user what I got the key---------------------
# echo "you key is '${k}'."

### -----------to make sure that php is set up, otherwise shell would be stopped.------------------
if ! command -v php &>/dev/null; then
    echo -e "$FRED php could not be found!"
    echo "Please install php-cli in addvance."
    exit
fi

# Install
sudo curl -o /usr/bin/prophet https://raw.githubusercontent.com/tmplink/prophet_client/main/prophet.php
# Make executable
sudo chmod 777 /usr/bin/prophet

echo -e "$FGRN Prophet installed".

## -----------------the last step, writing the key to /etc/rc.local for running automatically.---------------
## Not only does "rc.local" work on Centos, but also "rc.local" works on Debian.
sudo echo "prophet -k ${k} -b" >>/etc/rc.local

## ----------------to start this service immediately.----------------
nohup sudo prophet -k ${k} -b -d 0 >/dev/null 2>&1 &
echo -e "$FGRN Prophet service started."
