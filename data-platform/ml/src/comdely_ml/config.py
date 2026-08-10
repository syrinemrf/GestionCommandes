from __future__ import annotations

import os
from dataclasses import dataclass
from datetime import date
from urllib.parse import parse_qsl, urlencode, urlsplit, urlunsplit


class ConfigurationError(ValueError):
    '''Raised when the ML dataset configuration is invalid.'''


def _postgresql_url(value: str) -> str:
    if value.startswith('postgres://'):
        value = 'postgresql+psycopg://' + value.removeprefix('postgres://')
    elif value.startswith('postgresql://'):
        value = 'postgresql+psycopg://' + value.removeprefix('postgresql://')

    parts = urlsplit(value)
    query = [
        (key, item)
        for key, item in parse_qsl(parts.query, keep_blank_values=True)
        if key.lower() not in {'serverversion', 'charset'}
    ]
    return urlunsplit(
        (parts.scheme, parts.netloc, parts.path, urlencode(query), parts.fragment)
    )


def _date(env: dict[str, str], name: str, default: str) -> date:
    try:
        return date.fromisoformat(env.get(name, default).strip())
    except ValueError as exc:
        raise ConfigurationError(f'{name} must use the YYYY-MM-DD format') from exc


@dataclass(frozen=True, slots=True)
class Settings:
    database_url: str
    dataset_start: date = date(2024, 8, 4)
    dataset_end: date = date(2026, 8, 4)

    @classmethod
    def from_env(cls, environ: dict[str, str] | None = None) -> 'Settings':
        env = dict(os.environ if environ is None else environ)
        raw_url = env.get('DW_DATABASE_URL', '').strip()
        if not raw_url:
            raise ConfigurationError('Missing environment variable: DW_DATABASE_URL')

        database_url = _postgresql_url(raw_url)
        if not database_url.startswith('postgresql+psycopg://'):
            raise ConfigurationError('DW_DATABASE_URL must use PostgreSQL with psycopg')

        dataset_start = _date(env, 'ML_DATASET_START', '2024-08-04')
        dataset_end = _date(env, 'ML_DATASET_END', '2026-08-04')
        if dataset_start > dataset_end:
            raise ConfigurationError('ML_DATASET_START must not be after ML_DATASET_END')
        if dataset_end > date.today():
            raise ConfigurationError('ML_DATASET_END must not be in the future')

        return cls(database_url, dataset_start, dataset_end)
