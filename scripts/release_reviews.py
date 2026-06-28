from __future__ import annotations
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.services.reviews import release_due


def main() -> None:
    count = release_due()
    print(f"release_reviews: {count} review(s) released.")


if __name__ == "__main__":
    main()
