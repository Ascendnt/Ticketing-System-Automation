# Universal HelpDesk — AI ticketing prototype (free, solo build)

## What you're actually building (read this first)

The deck and email describe a full enterprise platform: service catalog, SLA matrix,
multi-department routing, ticket lifecycle, escalation rules. **Do not build that yet.**

Build the *smallest loop that proves the AI can do the thinking*:

```
employee fills a form
      │
      ▼
local LLM reads it → outputs a structured ticket (category, department, TAT, closure, etc.)
      │
      ├── routine request?  → keep the local result
      └── complex request?  → re-run with Gemini for a better result
      │
      ▼
ticket row appears in NocoDB (your "ticketing system" UI)
```

That's it. No SLA timers, no email intake, no notifications, no auth, no multi-dept
approvals. Get this loop working end-to-end, demo it, *then* add one thing at a time.
Resist the urge to build the SLA matrix on day one — it's the fastest way to never ship.

### The "hybrid AI" rule (matches your Engineering Note)

Routing is deterministic and based on the request's own category:

| Category (from the email's taxonomy) | Handled by | Why |
|---|---|---|
| Common / Special / Small-Scale | **local Ollama** | routine, cheap, private, offline |
| Mid-Scale / Large-Scale | **Gemini free tier** | needs real reasoning (BRD, scope, cross-team) |

The local model always runs first. Only the complex categories get escalated. This is
simpler and more reliable than asking a small local model to rate its own confidence.

### Is it really free?

- n8n, Ollama, NocoDB — self-hosted, free, no license.
- Gemini — **has a genuine free tier** (Google AI Studio, no card). Generous enough for a prototype.
- **Claude API is NOT free** (no free tier). Gemini is your free cloud escalation. You can
  swap Gemini → Claude later in the exact same node if the org decides to pay for it.

> Note: Gemini free-tier rate limits change over time. Check the current per-minute /
> per-day limits in AI Studio; for a prototype they're fine.

---

## Prerequisites

- Docker + Docker Compose (you already run these).
- ~4 GB RAM free for the local model (see model choices in Step 3). If your machine can't
  spare it, see the **"Weak machine?"** box in Step 3 — you can run Gemini-only first.
- The `docker-compose.yml` from this same folder.

---

## Step 1 — Start the stack

Put `docker-compose.yml` in an empty folder and run:

```bash
docker compose up -d
docker compose ps        # all three should be "running"
```

Give it a minute on first run (it pulls three images). Then open in your browser:

- n8n     → http://localhost:5678
- NocoDB  → http://localhost:8080

Ollama has no UI — it's an API on port 11434.

---

## Step 2 — (nothing to install manually, skip)

---

## Step 3 — Pull a local model into Ollama

```bash
docker compose exec ollama ollama pull qwen2.5:3b
```

`qwen2.5:3b` (~2 GB) is the sweet spot: small, fast on CPU, and genuinely good at
following instructions and emitting JSON. Options:

| Model | Size | Use when |
|---|---|---|
| `qwen2.5:3b` | ~2 GB | **default** — good JSON, runs on modest hardware |
| `llama3.1:8b` | ~5 GB | you have the RAM and want stronger reasoning |
| `qwen2.5:1.5b` | ~1 GB | very tight on RAM (quality drops a bit) |

Quick sanity check that the model answers:

```bash
docker compose exec ollama ollama run qwen2.5:3b "Reply with the single word: ready"
```

> **Weak machine?** Skip Ollama entirely for now. In the workflow below, point the FIRST
> extractor at Gemini too (instead of Ollama). You lose the "local" half of hybrid, but you
> prove the whole loop with zero local compute. Add Ollama back once the loop works.

---

## Step 4 — Get a free Gemini API key

1. Go to Google AI Studio → **Get API key** → create a key (no credit card).
2. Copy it somewhere safe. You'll paste it into n8n in Step 6.

---

## Step 5 — Set up NocoDB (your ticket store + UI)

1. Open http://localhost:8080 → create the admin account (first run).
2. Create a new **Base** → name it `HelpDesk`.
3. In it, create a **Table** named `Tickets` with these columns
   (delete the default sample columns you don't need):

   | Column name | Type |
   |---|---|
   | `Ticket ID` | (keep NocoDB's default auto ID) |
   | `Created At` | Created time (auto) |
   | `Status` | Single select → New, In Progress, Resolved, Closed |
   | `Requester` | Single line text |
   | `Requester Email` | Email |
   | `Submitting Department` | Single line text |
   | `Category` | Single select → Common Request, Special Request, Small-Scale Request, Mid-Scale Request, Large-Scale Request |
   | `Responsible Department` | Single line text |
   | `Request Title` | Single line text |
   | `Description` | Long text |
   | `Expected Deliverable` | Long text |
   | `Suggested TAT` | Single line text |
   | `Requirements Needed` | Long text |
   | `Closure Criteria` | Long text |
   | `AI Source` | Single select → Local, Gemini |

   (These 7 middle fields are exactly the columns from the email's sample table. `AI Source`
   is just so your demo shows which model handled each ticket. `Status` gives it a whiff of
   real ticket lifecycle.)

4. Get an API token: click your account (top area) → **Account Settings** → **Tokens** →
   **Add New Token** → copy it. n8n needs this in Step 6.

> Prefer zero extra infra? You can swap NocoDB for **Google Sheets** (n8n has a node for it)
> and skip the NocoDB container. NocoDB is recommended because it gives you a real
> grid/kanban ticket UI out of the box and stays self-hosted.

---

## Step 6 — Add the three credentials in n8n

Open n8n (http://localhost:5678), create your owner account, then go to
**Credentials → New** and add:

**a) Ollama**
- Type: `Ollama`
- Base URL: `http://ollama:11434`
  ⚠️ **Not** `localhost`. Inside the n8n container, `localhost` is n8n itself. Use the
  compose service name `ollama`. This is the #1 thing people get wrong.

**b) Google Gemini**
- Type: `Google Gemini (PaLM) API`
- API key: paste the key from Step 4.

**c) NocoDB**
- Type: `NocoDB API Token`
- Host / Base URL: `http://nocodb:8080`  (again, service name, not localhost)
- API Token: paste the token from Step 5.

---

## Step 7 — Build the workflow

Create a new workflow. Add nodes in this order. Node names may differ slightly by n8n
version — if a name doesn't match, search the node panel for the keyword in **bold**.

### 7.1 — Trigger: **Form Trigger** ("On form submission")
Add these form fields:
- `Requester Name` — text, required
- `Requester Email` — email, required
- `Submitting Department` — dropdown: IT, HR, TQA, Reports/WFM, Technical, Compliance, Operations
- `Request` — text (long), required — *"Describe your request in your own words."*

Save. n8n gives you a **Test URL** and a **Production URL** for the form — that's your intake page.

### 7.2 — **Information Extractor** (local pass)
This is an Advanced AI node. Attach an **Ollama Chat Model** sub-node to it (select your
Ollama credential, model `qwen2.5:3b`).

- **Text to analyze / input:** `{{ $json.Request }}`  (the form's Request field)
- **System prompt / instructions:** paste the block from *"Paste-ready: extractor prompt"* below.
- **Attributes / schema:** use the schema from *"Paste-ready: output schema"* below.
  (If your version has "Generate from JSON example," paste the JSON example there.)

### 7.3 — **If** node (is this complex?)
- Combinator: **OR**
- Condition 1: `{{ $json.output.category }}` **equals** `Mid-Scale Request`
- Condition 2: `{{ $json.output.category }}` **equals** `Large-Scale Request`

TRUE branch = complex → Gemini. FALSE branch = routine → keep local result.

### 7.4 — TRUE branch: **Information Extractor** (Gemini pass)
Same as 7.2 but attach a **Google Gemini Chat Model** sub-node (model `gemini-2.0-flash`
or the current free flash model). Use the **same** prompt and schema.
- **Input:** reference the original request from the Form Trigger, not the local output:
  `{{ $('On form submission').item.json.Request }}`
  (adjust the node name in quotes if yours differs)

### 7.5 — Two **NocoDB** nodes: Create Row
Add one on the TRUE branch (after Gemini) and one on the FALSE branch. Both: operation
**Create**, pick your `HelpDesk` base and `Tickets` table, then map fields.

FALSE branch (local result) field mapping:

| NocoDB column | Value |
|---|---|
| Status | `New` |
| Requester | `{{ $('On form submission').item.json['Requester Name'] }}` |
| Requester Email | `{{ $('On form submission').item.json['Requester Email'] }}` |
| Submitting Department | `{{ $('On form submission').item.json['Submitting Department'] }}` |
| Category | `{{ $json.output.category }}` |
| Responsible Department | `{{ $json.output.responsible_department }}` |
| Request Title | `{{ $json.output.request_title }}` |
| Description | `{{ $json.output.description }}` |
| Expected Deliverable | `{{ $json.output.expected_deliverable }}` |
| Suggested TAT | `{{ $json.output.suggested_tat }}` |
| Requirements Needed | `{{ $json.output.requirements_needed }}` |
| Closure Criteria | `{{ $json.output.closure_criteria }}` |
| AI Source | `Local` |

TRUE branch (Gemini result): **identical mapping**, except set `AI Source` = `Gemini`.
The `$json.output.*` references now read the Gemini extractor's output automatically
because it's the previous node on that branch.

### Final wiring

```
Form Trigger ──▶ Info Extractor (Ollama) ──▶ If
                                              ├─ true  ─▶ Info Extractor (Gemini) ─▶ NocoDB Create (AI Source = Gemini)
                                              └─ false ───────────────────────────▶ NocoDB Create (AI Source = Local)
```

Save the workflow. Toggle it **Active**.

---

## Step 8 — Test the loop

1. Open the Form Trigger's URL. Submit a **routine** request, e.g.:
   > "I can't log into my company email, need my password reset."
   → should be `Common Request`, IT, `AI Source = Local`.

2. Submit a **complex** one, e.g.:
   > "We need a new Salesforce automation to auto-assign leads by region, tested and deployed."
   → should be `Large-Scale Request`, Technical, `AI Source = Gemini`.

3. Open NocoDB → `Tickets` table → both rows should be there, fully filled in.

If a field comes back empty or the JSON breaks, it's almost always the prompt or the
schema — tweak the wording, re-run. Small models are picky; the prompt below is written
to be robust, but `llama3.1:8b` is more forgiving if you have the RAM.

You now have a working AI ticketing prototype. That's the milestone.

---

## Paste-ready: extractor prompt

Paste this verbatim into the **system prompt / instructions** of *both* Information
Extractor nodes (local and Gemini):

```
You are a support-desk request analyst for the "Universal HelpDesk". Read the employee's
request and produce ONE structured ticket.

Classify the request into exactly ONE category:
- "Common Request": routine, frequent, standard procedure (e.g. password reset,
  certificate of employment, standard report pull). Effort: minutes to 1 business day.
- "Special Request": non-routine but defined; needs some judgment or approval
  (e.g. a specific call audit, a document for official use). Effort: 1-2 business days.
- "Small-Scale Request": a small one-off task, single team, low effort. Effort: ~1 business day.
- "Mid-Scale Request": moderate effort with some coordination, spans a few days
  (e.g. an attendance report for a period, a social media campaign asset, process
  clarification). Effort: 1-3 business days.
- "Large-Scale Request": project-level; needs a business requirements document (BRD),
  stakeholder approval, and cross-functional work (e.g. new Salesforce automation,
  landing-page development). Effort: based on project scope.

Assign the RESPONSIBLE department (the team that will action it) from exactly this list:
IT, HR, TQA, Reports/WFM, Technical - Web, Technical - Marketing,
Technical - Salesforce/Tally, Technical - SQL, Compliance, Operations.

Then produce:
- request_title: a short, specific title.
- description: a 1-2 sentence clear restatement of the need.
- expected_deliverable: what "done" produces for the requester.
- suggested_tat: a realistic turnaround, e.g. "15-30 minutes", "1 business day",
  or "Based on project scope".
- requirements_needed: comma-separated info/approvals needed to start the work.
- closure_criteria: the condition that lets the ticket be closed.

Rules:
- Infer sensibly from context. Never invent employee data (IDs, names, dates).
- If required info is missing, say what's required inside requirements_needed.
- Return only the structured fields requested, nothing else.
```

---

## Paste-ready: output schema

Use these as the extractor **attributes** (name / type / description), or paste this JSON
example if your n8n version offers "Generate from JSON example":

```json
{
  "category": "Common Request | Special Request | Small-Scale Request | Mid-Scale Request | Large-Scale Request",
  "responsible_department": "the single team that will action the request",
  "request_title": "short specific title",
  "description": "1-2 sentence restatement of the need",
  "expected_deliverable": "what done produces for the requester",
  "suggested_tat": "realistic turnaround time",
  "requirements_needed": "comma-separated info or approvals needed to start",
  "closure_criteria": "condition that lets the ticket be closed"
}
```

All fields are strings.

---

## Later (only after the loop works — one at a time)

- **SLA timers:** a scheduled n8n workflow that flags tickets past their `Suggested TAT`.
- **Email intake:** an IMAP/Gmail trigger so people can email a request instead of using the form.
- **Notifications:** post new tickets to a Slack/Teams/email channel.
- **Status updates:** let the responsible department move `Status` in NocoDB; kanban view groups by it.
- **Swap/add Claude:** if the org pays for it, add a Claude Chat Model as a third tier for the hardest requests.

Ship the loop first. Then pick exactly one of these.
