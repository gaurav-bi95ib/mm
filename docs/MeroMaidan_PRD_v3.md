# MeroMaidan Product Requirements Document

- Version: 3.0
- Status: Approved business-model baseline
- Date: 9 August 2026
- Product: MeroMaidan sports venue marketplace and venue-management platform
- Primary market: Nepal

## 1. Executive summary

MeroMaidan connects players with sports venues and gives venue owners the operational tools needed to publish availability, accept bookings, manage fields, and monitor performance. This version establishes one simple venue subscription and two optional paid promotional services.

The commercial model is final:

| Commercial service | Price | Period | Entitlement |
|---|---:|---|---|
| Annual Venue Subscription | NPR 9,999 | 1 year | List and manage one venue |
| Recommended Venue | NPR 1,000 | 1 month | Clearly labelled location-based promotional visibility for one venue |
| Event Promotion | NPR 2,000 | 1 week | 1600x600 marketplace hero banner, CTA, and optional event coupon |

The three services are independent. Promotional payments do not extend or replace the annual subscription. The annual subscription does not include paid placement. Recommended Venue does not include an Event Promotion. There are no additional venue subscription tiers.

MeroMaidan has no customer feedback, review, or rating feature. Venue quality and marketplace governance rely on Super Admin approval, operational data, content moderation, booking performance, and policy enforcement.

## 2. Product objectives

1. Let players find a suitable venue by sport and location and complete a booking with a trustworthy price breakdown.
2. Let an approved venue owner operate one venue through a focused, multi-tenant dashboard.
3. Generate predictable platform revenue through the annual subscription.
4. Generate optional advertising revenue through Recommended Venue and Event Promotion without corrupting organic results.
5. Give Super Admin complete control, auditability, moderation, and financial visibility.
6. Preserve tenant isolation across every venue-owner record and action.

## 3. Scope and exclusions

### In scope

- Player registration, authentication, venue discovery, availability, booking, coupon validation, payment, invoice, favourites, and notifications.
- Venue-owner onboarding, one-venue management, grounds or courts, photos, facilities, hours, slots, prices, bookings, staff operations, customer records, reports, subscription, promotions, and payment history.
- Super Admin governance, CMS, commercial configuration, subscription monitoring, promotion moderation, payment monitoring, analytics, and audit logs.
- CMS-managed marketplace hero slides plus approved Event Promotion slides.
- Location-based Recommended Venue sections that are visibly labelled as paid placement.

### Out of scope

- Additional venue subscription tiers.
- A guaranteed organic search position.
- Customer feedback, reviews, star ratings, or owner replies.
- A predetermined Event Promotion price before stakeholder approval.
- Automatic publication of unmoderated promotional content.

## 4. Actors and access boundaries

### Player

Can browse venues, choose a ground, date, and slot, apply a valid coupon, see the price breakdown, book, pay, and manage personal bookings. A guest may browse and may book where guest booking remains enabled, but account-based eligibility rules require an authenticated player.

### Venue Owner

Can access only records belonging to the owner's tenant. The owner can manage the one subscribed venue, its operations, subscription, and promotional purchases. The owner cannot approve a promotion or change platform service prices.

### Operational Staff

Can access only assigned operational functions and assigned venues. Staff cannot buy subscriptions, change commercial configuration, approve promotions, or access another tenant.

### Super Admin

Can manage platform configuration, tenants, subscriptions, content, promotions, coupons when intervention is required, payments, analytics, and audit logs. Privileged actions must be audited.

### System jobs

Scheduled processes activate approved scheduled promotions, expire elapsed subscriptions and promotions, expire coupons, and maintain reporting aggregates. Jobs must be idempotent.

## 5. Commercial business rules

### BR-COM-001 - One annual plan

MeroMaidan offers one venue-owner subscription at NPR 9,999 for 12 months and one venue. The price, duration, and venue allowance are shown together wherever the plan appears.

### BR-COM-002 - Subscription entitlement

An active annual subscription permits the owner to list and manage one venue, including its grounds or courts, information, media, facilities, hours, prices, slots, bookings, standard operational staff, field activities, and relevant reports.

### BR-COM-003 - One-venue enforcement

The server enforces one venue per owner subscription. UI hiding alone is insufficient. Existing venue replacement or ownership correction requires an authorized administrative workflow rather than a second venue.

### BR-COM-004 - Promotional separation

Recommended Venue and Event Promotion are optional add-ons with separate lifecycle and payment records. Neither changes subscription entitlement.

### BR-COM-005 - Organic integrity

Recommended Venue is rendered in a dedicated labelled section. It must not permanently alter the organic result order. Organic discovery uses independent marketplace rules.

### BR-COM-006 - Fixed one-week Event Promotion

Event Promotion costs NPR 2,000 for exactly seven days. The amount, duration, and required 1600x600 banner dimensions are validated server-side and cannot be changed from the Owner or Super Admin UI.

## 6. Annual Venue Subscription

### Functional requirements

- FR-SUB-001: Super Admin can view the annual configuration: NPR 9,999, 12 months, one venue.
- FR-SUB-002: The owner can view subscription status, start date, expiry date, days remaining, entitlement, and payment history.
- FR-SUB-003: A successful annual payment creates an immutable subscription-period record and activates access.
- FR-SUB-004: Renewal requires a new payment and extends from the current expiry when the subscription is still active; otherwise it starts on the payment date.
- FR-SUB-005: A scheduled job or request-time check marks elapsed subscriptions expired.
- FR-SUB-006: Creation of an additional venue is denied server-side.
- FR-SUB-007: Super Admin can monitor active, pending, expired, cancelled, and suspended subscriptions.
- FR-SUB-008: Every subscription carries tenant, owner, venue where assigned, plan, amount, dates, status, and payment reference.

### Subscription states

`Pending Payment -> Active -> Expired`

Applicable exception states are `Suspended` and `Cancelled`. Renewal creates a new period rather than overwriting financial history.

## 7. Recommended Venue

### Definition

Recommended Venue is a one-month paid placement for a specific venue at NPR 1,000. It increases visibility within relevant location-based recommendation areas.

### Requirements

- FR-REC-001: Owner selects one owned, active venue and a start date.
- FR-REC-002: The server fixes the payable amount at NPR 1,000 and duration at one month.
- FR-REC-003: A promotion stores tenant, owner, venue, amount, start, expiry, status, approval data, and payment reference.
- FR-REC-004: Payment moves the placement to Pending Review.
- FR-REC-005: Super Admin can approve, reject, suspend, or cancel the placement.
- FR-REC-006: An approved future placement is Scheduled; an approved current placement is Active.
- FR-REC-007: A scheduled placement activates at its start date and expires automatically after its purchased period.
- FR-REC-008: Renewal requires a new purchase and payment.
- FR-REC-009: Only active placements for the requested location appear in the Recommended Venues section.
- FR-REC-010: The placement is identified as Recommended or Sponsored and is separate from organic results.
- FR-REC-011: Impression, click, and attributable booking events can be recorded.
- FR-REC-012: Owner and Super Admin can view status, dates, payment history, and performance.

## 8. Event Promotion and hero banner

### Definition

Event Promotion is a separate NPR 2,000 campaign for an event, campaign, or special offer. Each approved campaign runs for exactly seven days and may appear as a swipeable marketplace hero slide.

### Campaign fields

- Event title
- Venue
- Promotional image or banner
- Short description
- Event date
- Promotion start and expiry
- Discount display information
- Optional coupon or promo code
- CTA text: View Venue or Book Now
- CTA target resolved by the system to the venue detail and booking page

### Requirements

- FR-EVT-001: Owner can create an event campaign for an owned, active venue.
- FR-EVT-002: Owner must upload a 1600x600 JPG, PNG, or WebP banner of no more than 5 MB.
- FR-EVT-003: System validates required content, event date, CTA allow-list, exact media dimensions, file size, venue ownership, and the fixed seven-day period.
- FR-EVT-004: Event Promotion always displays and charges NPR 2,000 for one week.
- FR-EVT-005: A valid submitted campaign moves directly to Pending Payment.
- FR-EVT-006: Successful promotional payment moves the campaign to Pending Review.
- FR-EVT-007: Super Admin can approve, reject, suspend, cancel, schedule, and manage dates.
- FR-EVT-008: An approved banner is published only while its campaign is Active.
- FR-EVT-009: CTA opens the exact venue detail page and retains event and coupon attribution parameters.
- FR-EVT-010: Event slides participate in the same accessible swipe controls as CMS hero slides.
- FR-EVT-011: Impression, click, booking, and coupon-use analytics are attributable to the campaign where valid.
- FR-EVT-012: Inappropriate content can be suspended immediately; suspension unpublishes its banner and suspends its coupon.

## 9. Coupon system

### Coupon rules

A coupon belongs to a tenant, owner, venue, and optionally an Event Promotion. A coupon has a normalized unique code, type, value, optional minimum amount, optional maximum discount, usage limits, per-player limit, optional eligibility rules, validity dates, and status.

Supported discount types are percentage and fixed NPR amount. A percentage cannot exceed 100. A discount cannot reduce the pre-fee amount below zero.

### Validation sequence

1. Normalize the submitted code.
2. Verify the coupon exists.
3. Verify it belongs to the selected venue.
4. Verify status is Active.
5. Verify current time falls inside validity.
6. If linked to an event, verify the event is available.
7. Verify total usage limit.
8. Verify per-player or guest-phone usage limit.
9. Verify minimum booking amount.
10. Verify eligibility rules such as first booking or completed-booking threshold.
11. Calculate and cap discount.
12. Return a complete price breakdown.

Invalid coupons return a clear reason and leave the booking price unchanged. Final validation occurs again inside the booking/payment transaction to prevent stale availability or usage-limit races.

### Coupon requirements

- FR-CPN-001: Venue page contains a clearly visible Coupon / Promo Code field and Apply action.
- FR-CPN-002: Applying a valid coupon displays savings and the full breakdown.
- FR-CPN-003: Editing the code, venue, ground, date, or slot invalidates the previously calculated discount.
- FR-CPN-004: Client calculations are informational; the server is authoritative.
- FR-CPN-005: Successful booking increments usage once and creates an immutable coupon-usage row.
- FR-CPN-006: Failed or rolled-back bookings do not consume coupon usage.
- FR-CPN-007: Super Admin can suspend a coupon when intervention is required.
- FR-CPN-008: Expired coupons transition automatically to Expired.

## 10. Booking and pricing engine

### Customer journey

`Event Hero Banner -> View Venue / Book Now -> Venue Detail -> Select Ground -> Choose Date and Time -> Enter Coupon -> Validate Coupon -> Apply Discount -> Show Updated Price -> Confirm Booking -> Payment`

Direct organic or Recommended Venue traffic begins at venue discovery and follows the same authoritative booking path.

### Price order

`Base Venue Price -> Eligible Venue/Event Promotion Context -> Coupon Validation -> Discount -> Applicable Fees -> Applicable Taxes -> Final Payable Amount`

The current implementation uses zero fees and zero taxes until stakeholders configure applicable rules. It must not invent a fee or tax. All amount fields remain present so a future configured rule can be itemized without changing the contract.

### Price breakdown shown before payment

- Base venue price
- Coupon discount
- Fees
- Taxes
- Final payable amount
- Applied coupon code when present

### Booking requirements

- FR-BKG-001: Server obtains base price from the selected venue slot, not the submitted browser total.
- FR-BKG-002: Server checks maintenance blocks and conflicting active bookings.
- FR-BKG-003: Slot reservation, coupon use, booking, payment record, and invoice use transactional controls appropriate to the payment path.
- FR-BKG-004: Cash confirmation and eSewa confirmation store base, discount, fees, tax, and final amount.
- FR-BKG-005: A changed price or coupon state cancels payment confirmation and asks the customer to retry.
- FR-BKG-006: A successful booking has a unique reference and a consistent invoice amount.
- FR-BKG-007: Attributable campaign analytics never change the payable amount.

## 11. Payments

### Payment separation

Each payment identifies one service type: annual subscription, Recommended Venue, Event Promotion, or venue booking. Service type and resource ID are validated server-side.

### Requirements

- FR-PAY-001: Annual subscription payment amount must equal NPR 9,999.
- FR-PAY-002: Recommended Venue payment amount must equal NPR 1,000.
- FR-PAY-003: Event Promotion payment must equal NPR 2,000 and the campaign period must equal exactly seven days.
- FR-PAY-004: Booking payment must equal the server-recalculated final amount.
- FR-PAY-005: Successful promotional payments create payment-history records with tenant, owner, service, resource, amount, method, reference, status, and paid time.
- FR-PAY-006: Payment callbacks are idempotent in production and reject mismatched owner or resource relationships.
- FR-PAY-007: Payment status supports Initiated, Pending, Paid, Failed, Refunded, and Cancelled.
- FR-PAY-008: The local mock eSewa gateway is for testing only and must be replaced or isolated before production.

## 12. Venue Owner dashboard and workflows

### Subscription area

Shows the annual product, NPR 9,999/year, one-venue entitlement, status, dates, remaining days, renew action, features, and payment history. Optional promotion cards link to Promotions and do not appear as plan levels.

### Promotions area

Recommended Venue and Event Promotion have separate Owner pages. Recommended Venue provides purchase, start, expiry, status, payment, renewal guidance, and history. Event Promotion provides a 1600x600 upload, one-week period, CTA, optional coupon configuration, payment, moderation status, and campaign performance.

### Owner workflows

Annual subscription: `Open Subscription -> Verify amount and entitlement -> Pay -> Payment validation -> Active period -> Manage one venue`

Recommended Venue: `Open Recommended Venue page -> Select venue and start -> Pay NPR 1,000 -> Pending Review -> Admin sets placement -> Scheduled/Active -> Expired -> New purchase to renew`

Event Promotion: `Open Event Promotion page -> Upload 1600x600 banner -> Configure optional coupon -> Pay NPR 2,000 -> Pending Review -> Admin checks and sets start -> Scheduled/Active for seven days -> Expired`

## 13. Super Admin requirements

- FR-ADM-001: Display the annual service as NPR 9,999/year for one venue.
- FR-ADM-002: Monitor subscription periods, owners, venues, dates, status, and payments.
- FR-ADM-003: Display Recommended Venue as NPR 1,000/month and prevent a conflicting amount.
- FR-ADM-004: Display and enforce Event Promotion as NPR 2,000 for seven days.
- FR-ADM-005: Verify the submitted 1600x600 banner, exact venue, event details, CTA, and coupon before approval.
- FR-ADM-006: Approve or reject paid recommended placements and event campaigns.
- FR-ADM-007: Change campaign dates with audit entries where policy permits.
- FR-ADM-008: Suspend or cancel inappropriate campaigns and unpublish affected banners.
- FR-ADM-009: Suspend coupons when intervention is required.
- FR-ADM-010: View promotional and subscription payments.
- FR-ADM-011: View campaign impressions, clicks, bookings, and coupon uses.
- FR-ADM-012: Maintain audit logs for configuration, moderation, payment intervention, and status changes.
- FR-ADM-013: Manage marketplace CMS hero content independently of paid Event Promotions.

## 14. Promotion status model

Primary progression:

`Draft -> Pending Payment -> Pending Review -> Scheduled -> Active -> Expired`

Exception states:

- Rejected: moderation declined the submission.
- Suspended: an authorized administrator temporarily stopped an approved campaign.
- Cancelled: campaign terminated before normal expiry.

Rules:

- Payment is required before Pending Review.
- Approval is required before Scheduled or Active.
- Start time controls Scheduled to Active.
- End time controls Active or Scheduled to Expired.
- Rejected, Suspended, Cancelled, or Expired event banners are not published.
- Coupons linked to stopped or expired event campaigns cannot discount a booking.

## 15. Marketplace and UI/UX requirements

### Navigation and authentication

Player login and sign-up remain in the public account journey. Venue Owner Login and Super Admin Login are separate entry points. About content opens on its own navigation page and is not duplicated on the homepage.

### Homepage

- The announcement strip is removed.
- The hero is swipeable with previous, next, dot, touch, keyboard, and automatic progression controls.
- CMS slides and approved active Event Promotion slides use one visual system.
- Paid event slides show event date, venue, discount/coupon information where configured, and a direct CTA.
- Organic venues and Recommended Venues are visually separated.
- Recommended cards carry a visible Recommended or Sponsored label.
- The page does not show customer feedback, reviews, or ratings.

### Venue detail

- Facilities render as accessible cards, not an unstructured text line.
- Booking date and slots have loading, empty, error, available, selected, and full states.
- Coupon field appears near customer and payment information.
- Price breakdown updates only after successful validation.
- Validation errors are plain, specific, and preserve the original price.
- Payment CTA is disabled until a date and available slot are selected.
- Responsive layout keeps booking actions and total visible without overlap.

### UI quality

All commercial screens use consistent service names, price formatting, status labels, empty states, responsive layouts, focus indicators, readable contrast, and confirmation/error messages. NPR prices use thousands separators. Dates use a consistent Nepal-friendly presentation while database values remain ISO-formatted.

## 16. CMS requirements

- FR-CMS-001: Super Admin can create, edit, publish, unpublish, order, and delete CMS hero slides.
- FR-CMS-002: Hero slide fields include title, subtitle, body, image, CTA text, CTA URL, published status, and sort order.
- FR-CMS-003: CMS slides cannot overwrite paid campaign records.
- FR-CMS-004: Event Promotion slides are generated from approved campaign and banner records.
- FR-CMS-005: Unsafe URL schemes and unsupported media must be rejected.
- FR-CMS-006: Removing a CMS slide must not delete venue or promotion data.

## 17. API design

| Endpoint or service | Method | Purpose | Key authorization |
|---|---|---|---|
| `/api/grounds.php` | GET | Organic grounds plus recommendation metadata | Public; published active venues only |
| `/api/search_venues.php` | GET | Filtered organic venue discovery | Public; recommendation does not rewrite organic order |
| `/api/venue_detail.php` | GET | Venue, media, facilities, and date slots | Public; active venue only |
| `/api/validate_coupon.php` | POST | Validate coupon and return breakdown | Public/player context; server price rules |
| `/api/book.php` | POST | Confirm cash/direct booking | Validated venue, slot, coupon, and transaction |
| `/api/track_promotion.php` | POST | Record impression or click | Allow-listed event types and active campaign |
| `/esewa/payment.php` | POST/GET | Prepare mock payment | Owner for services; booking data validation |
| `/esewa/process.php` | POST | Confirm mock payment and resource | Server revalidation and transaction |
| Owner promotion actions | POST | Create placement/campaign/coupon | Authenticated owner and tenant ownership |
| Super Admin promotion actions | POST | Moderate campaign/coupon | Super Admin plus CSRF and audit |

All state-changing web forms require CSRF protection. JSON endpoints validate method, content type where relevant, authentication or public scope, input types, ownership, and status transition.

## 18. Data model

### VenueSubscription

`id, tenant_id, owner_id, venue_id, plan_id, amount_npr, starts_at, expires_at, status, payment_reference, created_at, updated_at`

### RecommendedVenuePromotion

`id, tenant_id, owner_id, venue_id, amount_npr, starts_at, expires_at, status, approved_by, approved_at, rejection_reason, payment_reference, created_at, updated_at`

### EventPromotion

`id, tenant_id, owner_id, venue_id, title, short_description, event_date, promotion_starts_at, promotion_expires_at, discount_label, cta_text, amount_npr, status, approved_by, approved_at, rejection_reason, created_at, updated_at`

### PromotionHeroBanner

`id, event_promotion_id, image_url, alt_text, is_published, created_at, updated_at`

### Coupon

`id, tenant_id, owner_id, venue_id, event_promotion_id, code, discount_type, discount_value, minimum_booking_amount, maximum_discount_amount, usage_limit, usage_limit_per_player, uses_count, eligibility_json, valid_from, valid_to, status, created_at, updated_at`

### CouponUsage

`id, coupon_id, booking_id, player_id, tenant_id, original_amount, discount_amount, final_amount, used_at`

### PromotionPayment

`id, tenant_id, owner_id, service_type, service_id, amount_npr, payment_method, provider_reference, status, paid_at, created_at`

### PromotionAnalytics

`id, tenant_id, promotion_type, promotion_id, event_type, player_id, booking_id, event_date, metadata_json, created_at`

### Booking additions

`base_price, coupon_id, coupon_code, discount_amount, fees_amount, tax_amount, total_price`

### Integrity requirements

- Tenant, owner, and venue relationships must agree before insert or update.
- Coupon code is unique after normalization.
- Coupon usage is unique per coupon and booking.
- Dates require start before expiry.
- Money uses decimal database types and server-side rounding to two decimals.
- Foreign keys protect core relationships; immutable payment/audit history uses controlled retention.
- Public queries return only fields required by the client.

## 19. Multi-tenant security and controls

- SEC-001: Owner queries include authenticated owner or tenant scope.
- SEC-002: Venue IDs from the browser are verified against owner scope.
- SEC-003: Super Admin functions require the Super Admin session and CSRF token.
- SEC-004: Passwords use PHP password hashing; secrets are not embedded in client code.
- SEC-005: File uploads validate MIME from file content, size, extension allow-list, generated filename, and controlled directory.
- SEC-006: Output is escaped; database commands use prepared statements.
- SEC-007: Payment amount and entitlement are resolved server-side.
- SEC-008: Promotion transitions use allow-lists and are audited.
- SEC-009: Analytics endpoints validate campaign type, ID, status, and allowed event type.
- SEC-010: Rate limiting is required for authentication, coupon validation, booking, and analytics ingestion in production.

## 20. Analytics and reporting

Owner campaign reporting includes status, validity, impressions, clicks, attributable bookings, coupon uses, and paid amount. Super Admin reporting can aggregate those metrics across venue, location, owner, campaign, and date.

Derived metrics include click-through rate and coupon conversion rate. Reports must distinguish recorded events from unique users and disclose attribution window rules once stakeholders configure them. Analytics failure must not block page rendering or booking.

## 21. Non-functional requirements

- NFR-001: Core public pages target useful mobile rendering at 360 px width and desktop rendering at 1440 px.
- NFR-002: Booking and payment operations preserve financial consistency under concurrent attempts.
- NFR-003: Promotion and subscription expiry logic is idempotent.
- NFR-004: Marketplace APIs target a normal p95 response below 800 ms on the expected launch dataset, excluding third-party network delays.
- NFR-005: Hero and banner images use responsive sizing, lazy loading where appropriate, useful alt text, and fallbacks.
- NFR-006: Interactive controls are keyboard reachable and have visible focus.
- NFR-007: Errors are logged server-side without exposing secrets or stack traces to customers.
- NFR-008: Database backups, restore testing, retention, and audit-log access are documented before production.
- NFR-009: Production payments use verified provider callbacks, signature validation, idempotency keys, and reconciliation.

## 22. User scenarios

### US-001 - Annual activation

An approved owner pays NPR 9,999. The server verifies the amount and annual product, records payment, creates a 12-month Active subscription for one venue, and shows the expiry in the owner dashboard.

### US-002 - Recommended placement in Biratnagar

The owner of a Biratnagar venue buys Recommended Venue for NPR 1,000. After payment and approval, it appears in the labelled Recommended Venues section when the marketplace context matches Biratnagar. Organic results remain independently ordered. The placement expires after one month.

### US-003 - One-week Event Promotion purchase

An owner selects an exact venue, uploads a 1600x600 banner, enters event and optional coupon details, and pays NPR 2,000. The paid campaign waits for Super Admin review.

### US-004 - Approved event and valid coupon

Super Admin downloads and checks the banner, verifies the venue and coupon, then sets the campaign start. The hero and coupon are active for the same seven-day period. A player follows Book Now, applies the coupon, sees the discount and final amount, and completes payment. Usage and attribution are recorded once.

### US-005 - Invalid coupon

A player enters an expired or wrong-venue code. The system states why it is invalid, hides any discount breakdown, leaves the base amount unchanged, and refuses a manipulated discounted request.

### US-006 - Campaign suspension

Super Admin suspends an inappropriate active event. Its hero banner stops appearing and its linked coupon becomes unusable. The action and reason are recorded in audit history.

## 23. Acceptance criteria

### Commercial consistency

- AC-COM-001: Every subscription UI and API exposes only NPR 9,999/year and one venue.
- AC-COM-002: Recommended Venue always displays and charges NPR 1,000/month.
- AC-COM-003: Event Promotion always displays and charges NPR 2,000 for exactly seven days.
- AC-COM-004: The three services have distinct records, statuses, payments, and dashboard presentation.
- AC-COM-005: No customer feedback, review, or rating control or content appears anywhere in the product.

### Recommended Venue

- AC-REC-001: A paid approved placement appears only during its active period and matching location context.
- AC-REC-002: It is visibly labelled and is not merged into organic order.
- AC-REC-003: It expires automatically and renewal requires a new payment.

### Event Promotion

- AC-EVT-001: A Draft or unpaid campaign never appears in the hero.
- AC-EVT-002: A paid but unapproved campaign never appears in the hero.
- AC-EVT-003: Active approved banner shows required event information and working venue CTA.
- AC-EVT-004: Expired, rejected, suspended, or cancelled campaigns do not appear.
- AC-EVT-005: Owner and Super Admin can see status, payment, dates, and performance.

### Coupons and pricing

- AC-CPN-001: Valid coupon calculates the documented breakdown from the authoritative slot price.
- AC-CPN-002: Missing, inactive, early, expired, wrong-venue, exhausted, ineligible, and below-minimum coupons do not change price.
- AC-CPN-003: Booking revalidates the coupon and amount at confirmation.
- AC-CPN-004: Successful use creates one usage row and increments the count once.
- AC-CPN-005: Cash and eSewa paths persist matching base, discount, fees, taxes, and final amount.

### Security and tenancy

- AC-SEC-001: An owner cannot create or modify another owner's venue, campaign, coupon, or payment.
- AC-SEC-002: A browser-supplied amount cannot change subscription, promotion, or booking price.
- AC-SEC-003: Moderation and configuration actions require Super Admin authorization and produce audit entries.

## 24. Requirement traceability

| Business goal | Requirements | Data | Primary UI/API | Acceptance |
|---|---|---|---|---|
| One annual venue service | BR-COM-001 to 003, FR-SUB-001 to 008 | VenueSubscription, subscription plan | Owner Subscription, Admin Commercial Services, eSewa service payment | AC-COM-001, AC-SEC-002 |
| Paid location visibility | BR-COM-004 to 005, FR-REC-001 to 012 | RecommendedVenuePromotion, PromotionPayment, PromotionAnalytics | Owner Promotions, Admin Promotions, grounds API | AC-REC-001 to 003 |
| Paid event hero campaign | BR-COM-006, FR-EVT-001 to 012 | EventPromotion, PromotionHeroBanner, PromotionPayment | Swipe hero, Owner Promotions, Admin Promotions | AC-EVT-001 to 005 |
| Coupon discount | FR-CPN-001 to 008 | Coupon, CouponUsage, Booking price fields | Venue booking, validate coupon API | AC-CPN-001 to 005 |
| Price integrity | FR-BKG-001 to 007, FR-PAY-001 to 008 | Booking, Invoice, payment records | Booking API and payment callbacks | AC-SEC-002, AC-CPN-005 |
| Tenant governance | SEC-001 to 010, FR-ADM-001 to 013 | Tenant-linked records, AuditLog | Owner and Super Admin panels | AC-SEC-001 to 003 |
| Professional marketplace | FR-CMS-001 to 006, NFR-001 to 006 | CMS content and event banners | Homepage and venue page | AC-EVT-003, AC-COM-005 |

## 25. Migration and release requirements

1. Back up the production database.
2. Apply the CMS, commercial-model, and product-cleanup migrations in order.
3. Convert all venue owners to the single annual plan and create subscription-period records according to approved migration policy.
4. Remove obsolete subscription products and legacy generic promotion data after validation.
5. Remove obsolete customer feedback/review data according to retention approval.
6. Verify foreign keys, indexes, tenant links, dates, and amounts.
7. Deploy server code before exposing new owner and Super Admin navigation.
8. Run smoke, authorization, coupon, payment, concurrency, responsive, and accessibility tests.
9. Reconcile test payments and verify that no test campaign remains publicly active.
10. Monitor errors, payment failures, promotion activation, and coupon rejection rates after release.

## 26. Stakeholder decisions still open

These items remain configurable or TBC and must not be guessed:
- Applicable booking fee and tax policies.
- Production payment provider credentials and callback contract.
- Promotion moderation service-level target.
- Analytics attribution window and unique-impression policy.
- Coupon eligibility options exposed in the first production release.

None of these open decisions changes the final separation between the annual subscription, Recommended Venue, and Event Promotion.
