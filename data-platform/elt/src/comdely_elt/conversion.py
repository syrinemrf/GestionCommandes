from __future__ import annotations

import json
from datetime import datetime, timezone
from decimal import Decimal
from typing import Any, Mapping
from zoneinfo import ZoneInfo

from sqlalchemy import Boolean, DateTime, Numeric, Table
from sqlalchemy.dialects.postgresql import JSONB


def convert_datetime(value: Any, source_timezone: ZoneInfo) -> datetime | None:
    if value is None:
        return None
    if isinstance(value, str):
        value = datetime.fromisoformat(value)
    if not isinstance(value, datetime):
        raise TypeError(f"Expected datetime-compatible value, got {type(value)!r}")
    if value.tzinfo is None:
        value = value.replace(tzinfo=source_timezone)
    return value.astimezone(timezone.utc)


def convert_decimal(value: Any) -> Decimal | None:
    if value is None:
        return None
    if isinstance(value, Decimal):
        return value
    return Decimal(str(value))


def convert_boolean(value: Any) -> bool | None:
    if value is None:
        return None
    if isinstance(value, str):
        return value.strip().lower() not in {"", "0", "false", "no"}
    return bool(value)


def convert_json(value: Any) -> Any:
    if value is None or isinstance(value, (dict, list, int, float, bool)):
        return value
    if isinstance(value, (bytes, bytearray)):
        value = value.decode("utf-8")
    if isinstance(value, str):
        return json.loads(value)
    raise TypeError(f"Expected JSON-compatible value, got {type(value)!r}")


def normalize_row(
    target: Table,
    source_row: Mapping[str, Any],
    source_timezone: ZoneInfo,
) -> dict[str, Any]:
    normalized: dict[str, Any] = {}
    for column in target.columns:
        if column.name in {"extracted_at", "etl_run_id"}:
            continue
        value = source_row[column.name]
        if isinstance(column.type, DateTime):
            value = convert_datetime(value, source_timezone)
        elif isinstance(column.type, Numeric):
            value = convert_decimal(value)
        elif isinstance(column.type, Boolean):
            value = convert_boolean(value)
        elif isinstance(column.type, JSONB):
            value = convert_json(value)
        normalized[column.name] = value
    return normalized
