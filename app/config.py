from pydantic_settings import BaseSettings
from functools import lru_cache


class Settings(BaseSettings):
    # Database
    DB_HOST: str = "localhost"
    DB_PORT: int = 3306
    DB_NAME: str = "enternstech"
    DB_USER: str = "root"
    DB_PASS: str = ""

    # Security
    SECRET_KEY: str = "change-me-in-production"
    JWT_ALGORITHM: str = "HS256"
    JWT_EXPIRE_MINUTES: int = 480  # 8 hours
    RESET_TOKEN_EXPIRY_MINUTES: int = 60    # forgot-password links
    SET_TOKEN_EXPIRY_MINUTES: int = 1440    # set-password links (24 h)

    # Razorpay
    RAZORPAY_KEY_ID: str = ""
    RAZORPAY_KEY_SECRET: str = ""

    # Email
    SMTP_HOST: str = "smtp.gmail.com"
    SMTP_PORT: int = 587
    SMTP_USER: str = ""
    SMTP_PASS: str = ""
    SMTP_FROM_NAME: str = "Enterns Tech"
    SMTP_FROM_EMAIL: str = "noreply@enternstech.com"
    ADMIN_EMAIL: str = "admin@enternstech.com"
    MENTOR_EMAIL: str = "mentor@enternstech.com"

    # App
    APP_BASE_URL: str = "http://localhost:8000"
    APP_ENV: str = "development"

    # Plan catalogue — paise (INR * 100)
    PLAN_PRICES: dict = {
        "basic":       15000000,
        "elite":       25000000,
        "premium":     35000000,
        "accelerator": 55000000,
        "starter":     37500000,
    }
    PLAN_SESSIONS: dict = {
        "basic": 4, "elite": 6, "premium": 8, "accelerator": 8, "starter": 6,
    }
    PLAN_CATALOG: dict = {
        "basic":       {"name": "Basic Plan",              "price_display": "₹1,50,000", "paise": 15000000, "sessions": 4},
        "elite":       {"name": "Elite Plan",              "price_display": "₹2,50,000", "paise": 25000000, "sessions": 6},
        "premium":     {"name": "Premium Plan",            "price_display": "₹3,50,000", "paise": 35000000, "sessions": 8},
        "accelerator": {"name": "Career Accelerator Combo","price_display": "₹5,50,000", "paise": 55000000, "sessions": 8},
        "starter":     {"name": "Career Starter Combo",   "price_display": "₹3,75,000", "paise": 37500000, "sessions": 6},
    }

    PSY_LINK_EXPIRY_DAYS: int = 7

    class Config:
        env_file = ".env"
        extra = "ignore"


@lru_cache()
def get_settings() -> Settings:
    return Settings()


settings = get_settings()
