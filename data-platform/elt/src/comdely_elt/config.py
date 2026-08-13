from __future__ import annotations

import os
from dataclasses import dataclass
from urllib.parse import parse_qsl, urlencode, urlsplit, urlunsplit
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError


class ConfigurationError(ValueError):
    """Raised when mandatory ELT configuration is missing or invalid."""


def _required(env: dict[str, str], name: str) -> str:
    value = env.get(name, "").strip()
    if not value:
        raise ConfigurationError(f"Missing environment variable: {name}")
    return value


def _normalize_source_url(url: str) -> str:
    if url.startswith("mysql://"):
        url = "mysql+pymysql://" + url.removeprefix("mysql://")
    elif url.startswith("mariadb://"):
        url = "mysql+pymysql://" + url.removeprefix("mariadb://")

    parts = urlsplit(url)
    query = [
        (key, value)
        for key, value in parse_qsl(parts.query, keep_blank_values=True)
        if key.lower() != "serverversion"
    ]
    return urlunsplit(
        (parts.scheme, parts.netloc, parts.path, urlencode(query), parts.fragment)
    )


def _normalize_target_url(url: str) -> str:
    if url.startswith("postgres://"):
        return "postgresql+psycopg://" + url.removeprefix("postgres://")
    if url.startswith("postgresql://"):
        return "postgresql+psycopg://" + url.removeprefix("postgresql://")
    return url


@dataclass(frozen=True, slots=True)
class Settings:
    source_database_url: str
    target_database_url: str
    batch_size: int = 500
    source_system: str = "comdely_mariadb"
    source_timezone: str = "Africa/Tunis"

    @classmethod
    def from_env(cls, environ: dict[str, str] | None = None) -> "Settings":
        env = dict(os.environ if environ is None else environ)
        source_url = _normalize_source_url(_required(env, "SOURCE_DATABASE_URL"))
        target_url = _normalize_target_url(_required(env, "DW_DATABASE_URL"))

        try:
            batch_size = int(env.get("ELT_BATCH_SIZE", "500"))
        except ValueError as exc:
            raise ConfigurationError("ELT_BATCH_SIZE must be an integer") from exc

        if batch_size <= 0:
            raise ConfigurationError("ELT_BATCH_SIZE must be greater than zero")
        if not source_url.startswith(("mysql+pymysql://", "mariadb+pymysql://")):
            raise ConfigurationError("SOURCE_DATABASE_URL must use MariaDB/MySQL with PyMySQL")
        if not target_url.startswith("postgresql+psycopg://"):
            raise ConfigurationError("DW_DATABASE_URL must use PostgreSQL with psycopg")

        source_timezone = env.get("ELT_SOURCE_TIMEZONE", "Africa/Tunis").strip()
        try:
            ZoneInfo(source_timezone)
        except ZoneInfoNotFoundError as exc:
            raise ConfigurationError(
                f"Unknown ELT_SOURCE_TIMEZONE: {source_timezone}"
            ) from exc

        source_system = env.get("ELT_SOURCE_SYSTEM", "comdely_mariadb").strip()
        if not source_system:
            raise ConfigurationError("ELT_SOURCE_SYSTEM cannot be empty")

        return cls(
            source_database_url=source_url,
            target_database_url=target_url,
            batch_size=batch_size,
            source_system=source_system,
            source_timezone=source_timezone,
        )
