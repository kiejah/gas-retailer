# Privacy notice and form consent text: DRAFT FOR REVIEW

> **Status: DRAFT (2026-11-01).** This is not legal advice. It was prepared as a starting point under Kenya's **Data Protection Act, 2019** and should be reviewed by the client, ideally with a Kenyan advocate, before it goes live.
>
> Items in `[SQUARE BRACKETS]` are placeholders waiting for real business details (owner items W-001/W-002). Items marked **⚖ Review** are judgement calls the client or their advocate should confirm.

---

## Part A: Short consent text shown on forms

These appear directly under each form, next to the submit button. The underlined "Privacy Policy" links to Part B.

**Quote request form (commercial/industrial)**
> ☐ I agree that [BUSINESS NAME] may use the details I've provided to respond to my quote request and contact me about it, as described in the [Privacy Policy]. *(required)*

**Cylinder exchange / refill request**
> By continuing to WhatsApp, you share your request (cylinder brand, size and delivery location) with [BUSINESS NAME] through WhatsApp, a service run by Meta. We use it only to arrange your exchange or refill. See our [Privacy Policy].

**Product enquiry via WhatsApp**
> You can edit this message before sending. Sending it shares your WhatsApp number and message with [BUSINESS NAME] via WhatsApp (Meta). See our [Privacy Policy].

**Checkout** (set in WooCommerce → Settings → Accounts & Privacy)
> Your personal data will be used to process your order, arrange delivery or pickup, and for other purposes described in our [Privacy Policy].

**⚖ Review:** The quote form uses an **unticked, required checkbox**, which gives clear, affirmative consent. The WhatsApp flows use **notice-at-the-point-of-action** instead, because the customer actively chooses to open WhatsApp and send a message themselves. The client's advocate should confirm this is enough.

---

## Part B: Privacy Policy page

### 1. Who we are
[BUSINESS NAME] ("we", "us") sells LPG products, accessories and equipment, and arranges cylinder exchanges and refills, in Nairobi, Kenya. For the purposes of the Data Protection Act, 2019, we are the **data controller** for the personal data described here.

**Contact for privacy questions:** [PRIVACY CONTACT EMAIL] · [PHONE] · [PHYSICAL ADDRESS], Nairobi.

**⚖ Review:** Check whether the business must **register with the Office of the Data Protection Commissioner (ODPC)** as a data controller. Registration thresholds depend on turnover, staff numbers and the type of processing. If registered, add the registration number here.

### 2. What personal data we collect

| When | What we collect |
|---|---|
| You request a quote | Name, organisation, phone, email, customer type (household, commercial or industrial), products and quantities, technical requirements, project description, delivery location, comments |
| You request a cylinder exchange or refill | Cylinder brand and size, exchange or new, delivery location, and, once you message us, your WhatsApp number and messages |
| You enquire about a product on WhatsApp | Your WhatsApp number, name as shown in WhatsApp, and your message |
| You place an order | Name, phone, email, delivery or billing address, order details, payment method (we do **not** store card or M-Pesa PINs) |
| You create an account (optional) | Name, email, password (stored encrypted), order history, saved addresses |
| You browse the site | Technical data needed to run the shop, such as your cart and session. See section 9, Cookies |

We don't ask for sensitive personal data (as defined in the Act), and we ask you not to include it in messages.

### 3. Why we use it, and on what basis

| Purpose | Lawful basis (Act, s.30) |
|---|---|
| Responding to quote requests and enquiries | Your **consent**, and steps you ask us to take before a contract |
| Arranging exchanges, refills, orders and delivery | **Performing a contract** with you |
| Keeping order and tax records | **Legal obligation** (e.g. Kenyan tax law) |
| Keeping the site secure and preventing spam or fraud | Our **legitimate interests** |

We do **not** sell your data, and we don't send marketing messages unless you separately agree to them.

### 4. Who receives it
- **Our sales team and the business owner**, to respond to you.
- **WhatsApp (Meta Platforms)**, when you choose to contact us through WhatsApp. Your messages are handled under WhatsApp's own terms and privacy policy.
- **Service providers** who run the shop for us, under appropriate safeguards: website hosting ([HOSTING PROVIDER]), email delivery ([EMAIL PROVIDER]) and payment processing ([PAYMENT PROVIDER, e.g. M-Pesa/Safaricom]), only as needed for their service.
- **Authorities**, where the law requires it.

### 5. Transfers outside Kenya
Some providers, including WhatsApp (Meta) and our email provider, may store or process data outside Kenya. When this happens we rely on the safeguards the Act allows, such as the provider's data protection commitments and your consent when you choose to contact us through those services.

**⚖ Review:** Confirm the transfer basis under Part VI of the Act and the related regulations, and name the actual providers once they are chosen (Phase 8: hosting and email).

### 6. How long we keep it

| Data | Kept for |
|---|---|
| Quote requests and enquiries that don't become orders | **[24 months]** from our last contact, then deleted |
| Orders and invoices | As long as Kenyan tax law requires **[at least 5 years]** |
| Customer accounts | Until you close your account, then as for orders |
| WhatsApp messages | According to our WhatsApp business practice: **[DEFINE]** |

**⚖ Review:** Confirm the retention periods. The figures in brackets are proposals.

### 7. Your rights
Under the Data Protection Act, 2019 you have the right to:
- be told how your data is used (this notice);
- **access** the personal data we hold about you;
- ask us to **correct** inaccurate data, or **delete** data that is no longer needed or was processed unlawfully;
- **object** to processing, and **withdraw consent** at any time (this doesn't affect processing already done);
- ask for your data in a portable format, where this applies.

To use any of these rights, contact [PRIVACY CONTACT EMAIL]. We'll respond within the time the law requires. If you're unhappy with how we handle your data, you can complain to the **Office of the Data Protection Commissioner** (www.odpc.go.ke).

### 8. Security
We protect your data with reasonable technical and organisational measures. These include encrypted connections (HTTPS), restricted staff access to quote and enquiry records, and keeping our software up to date. No system is perfectly secure, so please contact us if you think your data has been put at risk.

### 9. Cookies
Our shop uses **essential cookies** needed for it to work: your cart, checkout and login session. [If analytics or other non-essential cookies are added later, list them here and ask for consent first.]

### 10. Children
Our services are intended for adults. We don't knowingly collect data from children. LPG products should be bought by adults.

### 11. Changes
We may update this notice. The current version is always on this page.

*Last updated: [DATE OF APPROVAL]*

---

## Part C: How the site will support this (Phase 5 implementation notes)
- The consent checkbox on the quote form is required. The consent time and the policy version are saved with each quote request.
- Quote requests and logged enquiries are registered with WordPress's **personal data export and erase tools** (Tools → Export/Erase Personal Data), so access and deletion requests can be handled from the dashboard.
- **⚖ Review:** an automatic deletion setting for old unconverted enquiries (default [24 months]) is planned, so retention is enforced rather than just promised.
- The Privacy Policy page is created as an editable WordPress page, so the client can update this text without code.
