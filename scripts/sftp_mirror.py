#!/usr/bin/env python3
"""Recursive SFTP download. Credentials only via environment (never committed)."""

from __future__ import annotations

import os
import stat
from pathlib import Path

import paramiko


def require(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        raise SystemExit(f"Missing environment variable: {name}")
    return value


def posix_join(remote_dir: str, name: str) -> str:
    if remote_dir in (".", ""):
        return name
    return f"{remote_dir.rstrip('/')}/{name}"


def mirror(sftp: paramiko.SFTPClient, remote_dir: str, local_dir: Path) -> None:
    local_dir.mkdir(parents=True, exist_ok=True)
    for entry in sftp.listdir_attr(remote_dir):
        name = entry.filename
        if name in (".", ".."):
            continue
        remote_path = posix_join(remote_dir, name)
        mode = entry.st_mode
        if stat.S_ISDIR(mode):
            mirror(sftp, remote_path, local_dir / name)
        elif stat.S_ISREG(mode):
            dest = local_dir / name
            dest.parent.mkdir(parents=True, exist_ok=True)
            sftp.get(remote_path, str(dest))


def main() -> None:
    host = require("SFTP_HOST")
    port = int(os.environ.get("SFTP_PORT", "22"))
    user = require("SFTP_USER")
    password = require("SFTP_PASS")
    remote_root = os.environ.get("SFTP_REMOTE", ".")
    local_root = Path(require("SFTP_LOCAL")).resolve()

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        hostname=host,
        port=port,
        username=user,
        password=password,
        allow_agent=False,
        look_for_keys=False,
        timeout=60,
    )
    sftp = client.open_sftp()
    try:
        mirror(sftp, remote_root, local_root)
    finally:
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
