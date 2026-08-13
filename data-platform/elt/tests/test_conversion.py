from datetime import datetime, timezone
from decimal import Decimal
from zoneinfo import ZoneInfo

from comdely_elt.conversion import (
    convert_boolean,
    convert_datetime,
    convert_decimal,
    convert_json,
)


def test_datetime_is_converted_from_source_timezone_to_utc() -> None:
    result = convert_datetime(
        datetime(2026, 8, 4, 12, 30, 0),
        ZoneInfo("Africa/Tunis"),
    )

    assert result == datetime(2026, 8, 4, 11, 30, 0, tzinfo=timezone.utc)


def test_decimal_conversion_never_uses_binary_float_arithmetic() -> None:
    assert convert_decimal("19.000") == Decimal("19.000")
    assert convert_decimal(12.345) == Decimal("12.345")


def test_boolean_and_json_conversions() -> None:
    assert convert_boolean(0) is False
    assert convert_boolean("1") is True
    assert convert_json('{"taille":"M"}') == {"taille": "M"}
