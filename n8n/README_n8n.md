# n8n Automation Guide: Safiya's Makeover Bridal Anniversary Reminders

This directory contains the production-ready **n8n workflow export** (`n8n_bridal_anniversary_workflow.json`) to automate wedding anniversary reminders for Safiya's Makeover studio.

---

## 1. Overview & Architecture

The workflow runs on a daily schedule in the **Asia/Kolkata (IST)** timezone:
1. **Trigger**: Executes everyday at **09:00 AM IST** (`30 3 * * *` UTC).
2. **Fetch Due Reminders**: Calls `GET /api/v1/reminders/due` on the CRM using the secure `X-API-KEY`.
3. **Consent Gate**: Evaluates `whatsapp_consent` for each bride. If consent is `False`, automated messages are halted, leaving the record for manual review in the studio dashboard.
4. **Official WhatsApp Cloud API**: For opted-in brides, dispatches an approved Meta template message.
5. **Idempotency Acknowledgment**: Calls `POST /api/v1/reminders/mark-sent` with the unique `idempotency_key` (`CUST_{id}_Y{year}_{channel}_{reminder_type}`), guaranteeing that even if a workflow triggers twice, double messaging is strictly blocked.
6. **Owner Daily Digest**: Sends the daily summary email to the salon owner via the CRM's internal service.

---

## 2. Importing the Workflow into n8n

1. Log into your n8n instance (Self-hosted on Oracle VM or n8n Cloud).
2. Go to **Workflows** &rarr; Click **Add Workflow** (or `+`).
3. Click the top-right **Options** menu (`...`) &rarr; Select **Import from File**.
4. Upload `n8n_bridal_anniversary_workflow.json`.
5. Name the workflow: `Safiya Makeover - Bridal Anniversary Reminders`.

---

## 3. Configuring Credentials & Environment Variables

### A. CRM API Key (HTTP Header Auth)
1. In n8n, navigate to **Credentials** &rarr; **New Credential** &rarr; **Header Auth**.
2. Name: `Safiya CRM Header Auth`.
3. Set Header Name: `X-API-KEY`.
4. Value: The value of `CRM_API_KEY` from your `.env` or CRM Settings page.
5. Attach this credential to the **Fetch Due Anniversaries** and **Mark Sent in CRM** nodes.

### B. Meta / WhatsApp Business Cloud API (Optional)
If utilizing official automated WhatsApp greetings:
1. Register a Meta Developer App at [developers.facebook.com](https://developers.facebook.com).
2. Setup WhatsApp Cloud API and obtain:
   - `WHATSAPP_PHONE_NUMBER_ID`
   - `WHATSAPP_ACCESS_TOKEN` (System user permanent token)
3. Register and obtain Meta approval for the template:
   - **Template Name**: `anniversary_greeting_v1`
   - **Category**: `MARKETING` or `UTILITY`
   - **Language**: `en`
   - **Body text**:
     ```
     Dear {{1}}, Happy {{2}} Wedding Anniversary! May your journey together continue to be blessed with love, joy, and beauty. Warmest wishes from {{3}}.
     ```

> [!IMPORTANT]
> Never use unofficial WhatsApp Web puppeteer or session-cookie scraping tools. The official Meta WhatsApp Business Cloud API ensures phone number safety, compliance with Indian telecom regulations (TRAI DLT / Meta Commerce policies), and zero risk of phone number banning.

---

## 4. Testing the Integration

To verify the integration manually:
1. Open the workflow in n8n.
2. Click **Test Step** on the `Fetch Due Anniversaries` node.
3. Verify that the response returns `{ status: "success", count: X, reminders: [...] }`.
4. Turn the workflow **Active** (toggle in upper right).
