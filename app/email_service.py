import smtplib
import ssl
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from app.config import settings


_HTML_WRAPPER = """
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body {{ font-family: system-ui,-apple-system,sans-serif; background:#05080f; color:#f1f5f9; margin:0; padding:0; }}
  .container {{ max-width:600px; margin:0 auto; padding:32px 24px; }}
  .header {{ border-bottom:1px solid #1a2035; padding-bottom:20px; margin-bottom:24px; }}
  .logo {{ color:#22D3EE; font-size:1.4rem; font-weight:700; text-decoration:none; }}
  .content {{ line-height:1.6; }}
  .footer {{ margin-top:32px; padding-top:20px; border-top:1px solid #1a2035; color:#64748b; font-size:13px; }}
  a {{ color:#22D3EE; }}
  h2 {{ color:#22D3EE; margin:0 0 16px; }}
  p {{ margin:0 0 12px; color:#f1f5f9; }}
</style>
</head>
<body>
<div class="container">
  <div class="header"><a class="logo" href="{base_url}">Enterns Tech</a></div>
  <div class="content">{body}</div>
  <div class="footer">© Enterns Tech &bull; <a href="{base_url}">{base_url}</a></div>
</div>
</body>
</html>
"""


def send_mail(to: str, subject: str, html_body: str, bcc_admin: bool = True) -> bool:
    """Send HTML email via SMTP. Returns True on success."""
    try:
        msg = MIMEMultipart("alternative")
        msg["Subject"] = subject
        msg["From"] = f"{settings.SMTP_FROM_NAME} <{settings.SMTP_FROM_EMAIL}>"
        msg["To"] = to
        if bcc_admin and to != settings.ADMIN_EMAIL:
            msg["Bcc"] = settings.ADMIN_EMAIL

        full_html = _HTML_WRAPPER.format(
            base_url=settings.APP_BASE_URL,
            body=html_body,
        )
        msg.attach(MIMEText(full_html, "html", "utf-8"))

        ctx = ssl.create_default_context()
        with smtplib.SMTP(settings.SMTP_HOST, settings.SMTP_PORT) as server:
            server.ehlo()
            server.starttls(context=ctx)
            server.login(settings.SMTP_USER, settings.SMTP_PASS)
            recipients = [to]
            if bcc_admin and to != settings.ADMIN_EMAIL:
                recipients.append(settings.ADMIN_EMAIL)
            server.sendmail(settings.SMTP_FROM_EMAIL, recipients, msg.as_string())
        return True
    except Exception as exc:
        print(f"[email_service] Failed to send to {to}: {exc}")
        return False


def send_student_welcome(email: str, plan_name: str, set_password_url: str) -> bool:
    body = f"""
<h2>Welcome to Enterns Tech!</h2>
<p>Your enrolment for the <strong>{plan_name}</strong> is confirmed.</p>
<p style="margin:24px 0">
  <a href="{set_password_url}"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Set Your Password &rarr;
  </a>
</p>
<p style="color:#94a3b8;font-size:13px">This link expires in 24 hours. Use <em>Forgot Password</em> for a fresh link.</p>
<p>Student portal: <a href="{settings.APP_BASE_URL}/student">{settings.APP_BASE_URL}/student</a></p>
"""
    return send_mail(email, "Welcome to Enterns Tech — Set your password", body)


def send_mentor_approved(email: str, set_password_url: str) -> bool:
    body = f"""
<h2>You've been approved as a Mentor!</h2>
<p>Congratulations! Your mentor application has been reviewed and approved.</p>
<p style="margin:24px 0">
  <a href="{set_password_url}"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Set Your Password &rarr;
  </a>
</p>
<p style="color:#94a3b8;font-size:13px">This link expires in 24 hours.</p>
"""
    return send_mail(email, "You're approved — Set your password", body)


def send_mentor_rejected(email: str, reason: str = "") -> bool:
    reason_html = f"<blockquote style='border-left:3px solid #1a2035;padding-left:16px;color:#94a3b8'>{reason}</blockquote>" if reason else ""
    body = f"""
<h2>Mentor Application Update</h2>
<p>Thank you for applying to Enterns Tech as a mentor.</p>
<p>After careful review, we are unable to proceed with your application at this time.</p>
{reason_html}
<p>We appreciate your interest and encourage you to apply again in the future.</p>
"""
    return send_mail(email, "Your Mentor Application — Update", body)


def send_mentor_info_request(email: str, questions: str) -> bool:
    body = f"""
<h2>Mentor Application — More Information Needed</h2>
<p>Thank you for applying. We need a bit more information before we can proceed:</p>
<blockquote style="border-left:3px solid #22D3EE;padding-left:16px;color:#94a3b8;margin:16px 0">
{questions}
</blockquote>
<p>Please reply to this email with your answers.</p>
"""
    return send_mail(email, "Your Mentor Application — More Information Needed", body)


def send_mentor_application_received(application: dict) -> bool:
    body = f"""
<h2>New Mentor Application</h2>
<p><strong>Name:</strong> {application['full_name']}</p>
<p><strong>Email:</strong> {application['email']}</p>
<p><strong>Phone:</strong> {application.get('phone','')}</p>
<p><strong>LinkedIn:</strong> {application.get('linkedin','')}</p>
<p><strong>Tech Stack:</strong> {application.get('tech_stack','')}</p>
<p><strong>Available Slots:</strong> {application.get('available_slots','')}</p>
<p style="margin-top:20px">
  <a href="{settings.APP_BASE_URL}/admin?section=mentors&tab=applications"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Review Application &rarr;
  </a>
</p>
"""
    return send_mail(settings.MENTOR_EMAIL, f"New Mentor Application — {application['full_name']}", body)


def send_assessment_link(email: str, candidate_name: str, assessment_url: str) -> bool:
    body = f"""
<h2>Your Psychometric Assessment</h2>
<p>Dear {candidate_name},</p>
<p>You have been invited to complete a psychometric assessment for Enterns Tech.</p>
<p style="margin:24px 0">
  <a href="{assessment_url}"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Start Assessment &rarr;
  </a>
</p>
<p style="color:#94a3b8;font-size:13px">
  This assessment takes approximately 25–30 minutes. The link is valid for 7 days.
  Your results are confidential.
</p>
<p style="color:#94a3b8;font-size:13px">
  If the button doesn't work, copy this link: {assessment_url}
</p>
"""
    return send_mail(email, "Your Enterns Tech Assessment Invitation", body, bcc_admin=False)


def send_assessment_result_to_admin(candidate: dict, scores: dict) -> bool:
    traits = ""
    for t, label in [("trait_c","Conscientiousness"),("trait_e","Extraversion"),
                     ("trait_es","Emotional Stability"),("trait_o","Openness"),("trait_a","Agreeableness")]:
        val = scores.get(t)
        traits += f"<p><strong>{label}:</strong> {val if val is not None else 'N/A'}</p>"

    open_resp = ""
    for item in (scores.get("open_responses") or []):
        open_resp += f"<p><em>{item['question']}</em><br>{item['answer']}</p>"

    body = f"""
<h2>Assessment Submitted — {candidate['candidate_name']}</h2>
<p><strong>Email:</strong> {candidate['candidate_email']}</p>
<p><strong>Region:</strong> {candidate.get('region','')}</p>
<p><strong>Field:</strong> {candidate.get('field','')}</p>
<p><strong>Education Level:</strong> {candidate.get('education_level','')}</p>
<hr style="border-color:#1a2035;margin:16px 0">
<h3 style="color:#22D3EE">Scores</h3>
<p><strong>Strengths Index:</strong> {scores.get('strengths_index','N/A')}</p>
<p><strong>Preference Profile:</strong> {scores.get('preference_profile','')}</p>
<p><strong>Learning Index:</strong> {scores.get('learning_index','N/A')}</p>
<p><strong>Engagement Index:</strong> {scores.get('engagement_index','N/A')}</p>
<p><strong>Reasoning:</strong> {scores.get('reasoning_score','N/A')}/6 ({scores.get('reasoning_band','')})</p>
<p><strong>Overall Band:</strong> {scores.get('overall_band','')}</p>
<h3 style="color:#22D3EE">Big Five Traits</h3>
{traits}
{"<h3 style='color:#22D3EE'>Open Responses</h3>" + open_resp if open_resp else ""}
<p style="margin-top:20px">
  <a href="{settings.APP_BASE_URL}/admin?section=assessments&view={candidate['id']}"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     View Full Result &rarr;
  </a>
</p>
"""
    return send_mail(
        settings.ADMIN_EMAIL,
        f"Assessment Submitted — {candidate['candidate_name']}",
        body,
        bcc_admin=False
    )


def send_mentor_change_received(student_name: str, reason: str) -> bool:
    body = f"""
<h2>Mentor Change Request</h2>
<p><strong>Student:</strong> {student_name}</p>
<p><strong>Reason:</strong> {reason}</p>
<p style="margin-top:20px">
  <a href="{settings.APP_BASE_URL}/admin?section=requests"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Review Request &rarr;
  </a>
</p>
"""
    return send_mail(settings.ADMIN_EMAIL, f"Mentor Change Request — {student_name}", body, bcc_admin=False)


def send_mentor_change_approved(email: str, mentor_name: str) -> bool:
    body = f"""
<h2>Mentor Change Approved</h2>
<p>Your mentor change request has been approved.</p>
<p>Your new mentor is <strong>{mentor_name}</strong>.</p>
<p><a href="{settings.APP_BASE_URL}/student">Go to your portal &rarr;</a></p>
"""
    return send_mail(email, "Mentor Change Approved", body)


def send_mentor_change_denied(email: str, reason: str = "") -> bool:
    reason_html = f"<blockquote style='border-left:3px solid #1a2035;padding:12px 16px;color:#94a3b8'>{reason}</blockquote>" if reason else ""
    body = f"""
<h2>Mentor Change Request Update</h2>
<p>Your mentor change request could not be approved at this time.</p>
{reason_html}
<p>Please contact us if you have further questions.</p>
"""
    return send_mail(email, "Mentor Change Request — Update", body)
