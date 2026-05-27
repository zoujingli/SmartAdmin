#!/bin/bash

set -euo pipefail

cd "$(dirname "$0")"

# SmartAdmin Docker/1Panel 
# 二进制升级模板；实际环境只需调整本段连接、路径和容器变量。
USER="root"
PASS="PASSWORD"
SERVER="10.10.10.1"

LOCAL_FILE="../system-linux-x64"
REMOTE_TEMP="/tmp/system-linux-x64"
REMOTE_FILE="/www/1panel/apps/smart/system-linux-x64"
CONTAINER_PATH="/app/system-linux-x64"
CONTAINER_NAME="smart-server"

SSH_OPTS=(
    -o StrictHostKeyChecking=accept-new
    -o UserKnownHostsFile=/dev/null
    -o ConnectTimeout=10
    -o LogLevel=ERROR
)

log() {
    printf '\033[32m[INFO]\033[0m %s\n' "$1"
}

die() {
    printf '\033[31m[ERROR]\033[0m %s\n' "$1" >&2
    exit 1
}

step() {
    local desc="$1"
    shift

    log "$desc"
    "$@" || die "$desc 失败"
}

ssh_run() {
    sshpass -p "$PASS" ssh "${SSH_OPTS[@]}" "$USER@$SERVER" "$1"
}

upload() {
    sshpass -p "$PASS" scp "${SSH_OPTS[@]}" "$LOCAL_FILE" "$USER@$SERVER:$REMOTE_TEMP"
}

remote_upgrade() {
    sshpass -p "$PASS" ssh "${SSH_OPTS[@]}" "$USER@$SERVER" "bash -se" <<EOF_REMOTE
set -e
rm -rf "$REMOTE_FILE"
cp "$REMOTE_TEMP" "$REMOTE_FILE"
chmod +x "$REMOTE_FILE"
docker restart "$CONTAINER_NAME"
sleep 2
docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:release:restore --install
docker exec "$CONTAINER_NAME" "$CONTAINER_PATH" --self xadmin:website:publish
docker restart "$CONTAINER_NAME"
EOF_REMOTE
}

for cmd in sshpass ssh scp; do
    command -v "$cmd" >/dev/null 2>&1 || die "缺少依赖命令: $cmd"
done

[ -f "$LOCAL_FILE" ] || die "本地升级文件不存在: $LOCAL_FILE"

step "1/3 清理临时目录" ssh_run "rm -rf \"$REMOTE_TEMP\""
step "2/3 上传程序文件" upload
step "3/3 替换程序、升级发布、重启" remote_upgrade

log "升级完成"
