from __future__ import annotations
from datetime import datetime, timezone
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

COMMON_TZS: list[str] = [
    "Asia/Kolkata",
    "America/New_York",
    "America/Los_Angeles",
    "Europe/London",
    "Asia/Dubai",
    "Asia/Singapore",
    "Australia/Sydney",
    "UTC",
]


def valid_tz(name: str) -> bool:
    try:
        ZoneInfo(name)
        return True
    except (ZoneInfoNotFoundError, KeyError):
        return False


def to_utc(naive_local: datetime, tz: str) -> datetime:
    """Interpret a naive local datetime in tz, return naive UTC."""
    aware_local = naive_local.replace(tzinfo=ZoneInfo(tz))
    return aware_local.astimezone(timezone.utc).replace(tzinfo=None)


def from_utc(naive_utc: datetime, tz: str) -> datetime:
    """Return tz-aware local datetime from naive UTC."""
    aware_utc = naive_utc.replace(tzinfo=timezone.utc)
    return aware_utc.astimezone(ZoneInfo(tz))


def fmt_local(naive_utc: datetime, tz: str, fmt: str = "%a %d %b, %H:%M %Z") -> str:
    """Format a naive UTC datetime as a local-tz string."""
    if not naive_utc:
        return "—"
    try:
        return from_utc(naive_utc, tz).strftime(fmt)
    except Exception:
        return naive_utc.strftime("%a %d %b, %H:%M") + " UTC"
