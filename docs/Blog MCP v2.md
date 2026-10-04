# Blog MCP v2 — Tool Specification

Oct 4, 2026 · @Gheith

## 1. Context and goals

v2 makes a small edit cost a small request, makes every agent write reversible, and lets the author review agent changes before they go live. The spec is stack-agnostic: it defines tool contracts, behaviour and tests, not framework code.

### Current tools and their gaps

| Tool | What it does today | Gap found in use |
| --- | --- | --- |
| list-posts | Lists posts with id, title, slug, status, category | No search, no filters beyond status, no version or word count |
| get-post | Returns one post by id, content as raw HTML | No lookup by slug or URL; entities like `&quot;` and inline styles in the body |
| update-post | Replaces whole fields (title, content, status, tags, thumbnail) | Small fixes need the full body resent; the full body is echoed back; no overwrite protection |
| create-post / delete-post | Create and delete | No excerpt, slug or publish-date control; delete is irreversible |
| Comment tools | List, get, create, update, delete comments | Out of scope for v2 |

### What went wrong in a real session (2026-10-04)

An agent proofread post 18, "غراب فوق ياسمينة" (about 2,000 words, 33 paragraphs). It needed roughly 60 small fixes: tanween, hamza, two grammar errors and punctuation.

- The agent had to resend the entire HTML body to change about 60 short phrases. Any slip elsewhere in that body would have silently corrupted text no one meant to touch.
- update-post echoed the whole body back, so the story crossed the wire three times.
- The agent had the post URL but had to list all posts to find id 18.
- A style review of six posts required scraping six public pages, including menus and comment forms.
- The only backup of the original text was the chat transcript.
- The author had to approve the change in chat from a summary, without seeing a real diff.

### Goals

1. The cost of an edit is proportional to the change, not to the post's length.
2. No agent write can silently overwrite a newer human edit.
3. Every write is reversible, and agent changes can be staged for the author's approval.
4. Agents read clean text, and find posts by slug or URL.
5. The author's house style is available to every agent without repeating it.

Non-goals: no change to how the public site renders posts, and no proofreading logic inside the server. Judgement about the text stays with the agent and the author.

## 2. Design principles and shared conventions

Every tool in v2 follows the same six rules. Existing tool names (kebab-case) and parameter style (snake\_case) stay as they are.

1. **Version token.** Every post carries an integer `version`, starting at 1. It increments on every saved change from any source, including the dashboard. Every read and every write returns it.
2. **Compare-and-set writes.** Every write accepts an optional `if_version`. If it differs from the current version, nothing is written and the call fails with `version_conflict`.
3. **Lean responses.** Writes return a small receipt, never the full body. The previous behaviour is available with `verbose: true`.
4. **All or nothing.** A call either applies every change it carries or none of them.
5. **Every write is a revision.** Each saved change, from the MCP or the dashboard, stores a revision that can be inspected and restored (section 6).
6. **One error shape.** Every failure returns the same structure, and reports all problems found, not just the first.

### Write receipt (all write tools)

```json
{
  "post_id": 18,
  "version": 7,
  "updated_at": "2026-10-04T09:12:44Z",
  "status": "published",
  "revision_id": "01J9Z3W6Q8T4M2K7R5N1B0X3YC",
  "summary": "57 edits applied in 21 paragraphs"
}
```

### Error shape (all tools)

```json
{
  "error": {
    "code": "version_conflict",
    "message": "Post 18 is at version 8; the request expected 7.",
    "details": { "current_version": 8, "updated_at": "2026-10-04T09:15:02Z", "source": "dashboard" }
  }
}
```

`message` is for humans. `details` is for the agent and is listed per code in section 9.

### Backwards compatibility

- New parameters are optional. Calls written for v1 keep working, except that writes return the lean receipt unless `verbose: true` is set.
- A write without `if_version` is still accepted, but its revision is flagged `unguarded` so the author can spot it.
- Authentication and the API key scope do not change.

## 3. Content views

Storage stays HTML, exactly as the editor writes it today. get-post (and get-posts, search-posts) gain a `format` parameter that only changes what is returned, never what is stored.

| `format` | Returns | Agent uses it for |
| --- | --- | --- |
| `html` (default) | The stored HTML, unchanged | Structural changes through update-post |
| `text` | Plain text: entities decoded, tags removed, one block per paragraph, blocks separated by a blank line | Reading, style review, proofreading |
| `markdown` | Headings, bold, italic, links, lists and quotes as Markdown; alignment styles dropped | Reading posts that use formatting |
| `paragraphs` | A JSON array of blocks, each with its index, type, text and HTML | Targeting edits to a specific paragraph |

### Rules for every non-HTML view

- Entities are decoded: `&quot;` becomes `"`, `&nbsp;` becomes a no-break space.
- Characters are returned exactly as stored. No Unicode normalization, and harakat, shadda, tatweel and direction marks are kept.
- Block indexes are 1-based and follow document order. Each top-level block counts once: a paragraph, heading, list item, quote or image caption.
- Text in the `text` view is the same text edit-post matches against (section 4). What the agent reads is what it can quote.

### Example: `format: "paragraphs"`

```json
{
  "post_id": 18,
  "version": 6,
  "blocks": [
    { "index": 1, "type": "p", "text": "قبّلني ثم تمدّد بجانبي وأمسك بيدي، ...", "html": "<p>قبّلني ثم تمدّد بجانبي ...</p>" },
    { "index": 29, "type": "p", "text": "خطٌّ واحد.", "html": "<p style=\"text-align: justify;\">خطٌّ واحد.</p>" }
  ]
}
```

The `html` field per block can be omitted with `include_html: false` to keep the response small.

## 4. New tool: edit-post

edit-post applies a list of exact find-and-replace edits to a post's body in one atomic call. It is the default way for an agent to change existing text; update-post remains for structural changes and new bodies.

### Parameters

| Parameter | Type | Required | Meaning |
| --- | --- | --- | --- |
| `post_id` | integer | yes | The post to edit |
| `edits` | array, 1–200 items | yes | The edits, described below |
| `if_version` | integer | strongly advised | Fail with `version_conflict` if the post changed since it was read |
| `dry_run` | boolean, default `false` | no | Validate and return the diff without saving |
| `apply_to` | `live` (default) or `pending` | no | `pending` stages the change for author approval (section 6) |
| `whole_word` | boolean, default `false` | no | Applies to every edit unless the edit sets its own |
| `note` | string, max 200 chars | no | Stored on the revision, shown to the author |

Each item in `edits`:

| Field | Type | Required | Meaning |
| --- | --- | --- | --- |
| `old` | string, non-empty | yes | Exact text to find, as it appears in the `text` view |
| `new` | string, may be empty | yes | Replacement text; empty deletes `old` |
| `occurrence` | integer, 1-based | no | Which match to use when `old` appears more than once |
| `paragraph` | integer, 1-based | no | Restrict the search to one block index |
| `whole_word` | boolean | no | Overrides the call-level setting for this edit |

### Matching rules

1. Matching runs on decoded text, so the agent writes `"` and never `&quot;`. Tags are invisible to matching.
2. Comparison is exact, code point by code point. No case folding, no whitespace collapsing, no normalization, and harakat count as characters.
3. `old` must match exactly once within its scope. Zero matches fails with `edit_not_found`; several matches without `occurrence` fails with `edit_ambiguous`.
4. With `whole_word: true`, a match must not be preceded or followed by a letter. Arabic combining marks (U+064B–U+065F, U+0670) and tatweel (U+0640) count as part of the word, so `مباغتا` does not match inside `مباغتاً`.
5. Every `old` is located in the original text before any edit is applied. Edits never see each other's output, and overlapping matches fail with `edit_overlap`.
6. A match must sit inside one block. A match that crosses inline formatting (for example, half bold) fails with `edit_spans_markup` in v2.0.
7. `new` is inserted as text. Any `<` or `&` in it is escaped, so edit-post can never inject markup.
8. Block attributes such as `style="text-align: justify;"` are always preserved.

### Atomicity and errors

If any edit fails, nothing is written and the version does not change. The error lists every failing edit with its index, so the agent can fix them all in one retry.

```json
{
  "error": {
    "code": "edit_failed",
    "message": "2 of 57 edits could not be applied. Nothing was saved.",
    "details": {
      "failures": [
        { "index": 12, "code": "edit_not_found", "old": "ولا تستأذن أحد،", "near_matches": [ { "paragraph": 24, "text": "ولا تستأذن أحد،", "difference": "invisible character U+200F" } ] },
        { "index": 31, "code": "edit_ambiguous", "old": "أيضا", "count": 2, "paragraphs": [10, 17] }
      ]
    }
  }
}
```

For `edit_not_found`, the server returns up to 3 `near_matches` when the only differences are harakat, invisible characters or whitespace. This turns the most common Arabic mismatch into a one-step fix.

### Success response

The standard receipt (section 2) plus `applied` and `changed_paragraphs`. With `dry_run: true` the response is a diff instead, and nothing is saved:

```json
{
  "post_id": 18,
  "version": 6,
  "dry_run": true,
  "applied": 2,
  "diff": [
    { "paragraph": 8, "before": "...ولم نتجاوز القبل قط، سحبت يداه من التوغل...", "after": "...ولم نتجاوز القبل قط؛ سحبتُ يديه من التوغل..." },
    { "paragraph": 30, "before": "...أبلع قراراً صغير لحماية لطفل لم يأت.", "after": "...أبلع قراراً صغيراً لحماية طفل لم يأتِ." }
  ]
}
```

`before` and `after` show the changed span with about 40 characters of context on each side, not the full paragraph.

### Example request

```json
{
  "post_id": 18,
  "if_version": 6,
  "note": "Proofreading: tanween, hamza, punctuation",
  "edits": [
    { "old": "سحبت يداه من التوغل", "new": "سحبتُ يديه من التوغل" },
    { "old": "قراراً صغير لحماية لطفل", "new": "قراراً صغيراً لحماية طفل" },
    { "old": "مباغتا", "new": "مباغتاً", "whole_word": true },
    { "old": "أيضا", "new": "أيضاً", "occurrence": 2 }
  ]
}
```

## 5. Changes to update-post, create-post and delete-post

The existing write tools gain the shared conventions plus three writable metadata fields. All additions are optional.

### New parameters

| Parameter | Tools | Type | Meaning |
| --- | --- | --- | --- |
| `if_version` | update-post, delete-post | integer | Compare-and-set (section 2) |
| `verbose` | update-post, create-post | boolean, default `false` | Return the full post instead of the receipt |
| `note` | update-post, create-post | string, max 200 chars | Stored on the revision |
| `apply_to` | update-post | `live` (default) or `pending` | Stage title or content changes for approval |
| `excerpt` | update-post, create-post | string, max 300 chars | Listing card text and meta description |
| `slug` | update-post, create-post | string | URL path segment |
| `published_at` | update-post, create-post | ISO 8601 datetime | Publish date; a future date schedules the post |

### Field rules

- **excerpt.** Feeds `meta-description`, `og:description`, `twitter:description` and the listing card. When empty, the server generates one from the first paragraph, cut at a word boundary and ending with "…". Today's auto-excerpt cuts mid-word, for example "أبيع الكوارث كي أعيش. كل مساء، أقف أما...".
- **slug.** Must be unique; allowed characters are lowercase Latin letters, digits and hyphens, max 80. Changing the slug of a published post keeps the old slug as a permanent 301 redirect. A taken slug fails with `slug_taken`.
- **published\_at.** Setting a future date on a published post moves it to scheduled. Setting status to `published` with no date stamps the current time, as today.
- **apply\_to: pending** applies only to `title` and `content`. Metadata changes in the same call are rejected with `invalid_param`, so a staged change never half-applies.

### delete-post

delete-post becomes reversible: it archives by default and keeps all revisions. A permanent delete requires `permanent: true` together with a matching `if_version`.

## 6. Revisions and author review

Every saved change becomes a revision, and an agent can stage a change that only goes live when the author approves it in the dashboard. Agents can never approve their own staged changes.

### Revision record

Each write from the MCP or the dashboard stores one revision with: `revision_id`, `post_id`, `version`, `state`, `source` (`dashboard`, `mcp` or `api`), `client_name`, `note`, `unguarded` flag, `created_at`, and a full snapshot of title, content, excerpt, slug and status.

| `state` | Meaning |
| --- | --- |
| `applied` | Live now or was live at some point |
| `pending` | Staged by an agent, waiting for the author |
| `rejected` | The author declined it; live text untouched |
| `conflict` | Approved, but could not be applied to a newer version |
| `superseded` | A pending revision replaced by a newer one from the same client |

### New tools

| Tool | Parameters | Returns |
| --- | --- | --- |
| list-revisions | `post_id`, `state`?, `limit` (default 20) | Revisions newest first, without bodies |
| get-revision | `revision_id`, `format`?, `diff_against` (`previous` or `live`)? | The snapshot, a diff, or both |
| restore-revision | `post_id`, `revision_id`, `if_version` | Receipt; creates a new revision, never deletes history |

### Staged changes (`apply_to: "pending"`)

1. edit-post or update-post with `apply_to: "pending"` creates a pending revision based on the current version. The live post does not change.
2. The dashboard shows a banner on the post: "Pending change from Claude", with the note, a paragraph-level diff with character highlights, and Approve and Reject buttons.
3. On Approve, if the live version still equals the base version, the change applies and the version increments.
4. If the author edited the post in the meantime, the server replays the stored edit list from edit-post on the new text. If every edit still matches exactly once, it applies; otherwise the revision becomes `conflict`.
5. A pending revision from update-post (a whole new body) cannot be replayed, so it becomes `conflict` whenever the base version is stale.
6. The agent learns the outcome with list-revisions (`state: "pending"` or by `revision_id`).

A new pending revision from the same client on the same post marks the previous one `superseded`, so the author never reviews two competing versions.

&#91;embedded content: staged change review · 3 decisions, 3 outcomes\]

Only edit-post changes can be replayed on a newer version; a staged update-post body goes straight to Conflict when its base version is stale.

### Retention

Keep at least the last 100 revisions per post, and never prune the first published revision or any pending one.

## 7. Reading and lookup

An agent should reach any post in one call from whatever it has: an id, a slug or a public URL. It should also read several posts, or search them, without scraping the public site.

### get-post (extended)

| Parameter | Type | Meaning |
| --- | --- | --- |
| `post_id` | integer | As today |
| `slug` | string | For example `q3nbwPmo` |
| `url` | string | A full public URL, for example `https://gheith.me/posts/q3nbwPmo` |
| `format` | `html`, `text`, `markdown`, `paragraphs` | Section 3; default `html` |

Exactly one of `post_id`, `slug` or `url` is required; zero or several fail with `invalid_param`. A URL or slug that was renamed resolves through its redirect. The response adds `version`, `excerpt`, `word_count`, `reading_time_minutes`, `published_at`, `updated_at` and `url`.

### get-posts (new)

Reads up to 20 posts in one call, for style reviews and cross-post work.

| Parameter | Type | Meaning |
| --- | --- | --- |
| `post_ids` or `slugs` | array, max 20 | Which posts; or omit and use the filters |
| `category_id`, `status` | filters | Same meaning as in list-posts |
| `format` | default `text` | Section 3 |
| `max_chars_per_post` | integer, optional | Truncates each body, flagged with `truncated: true` |

### search-posts (new)

| Parameter | Type | Meaning |
| --- | --- | --- |
| `query` | string | Words or a phrase to find in titles and bodies |
| `status`, `category_id` | filters | Optional |
| `limit` | integer, default 20 | Max results |

Each result returns `post_id`, `title`, `slug`, `status`, `match_count` and up to 3 snippets of about 120 characters around the matches.

Search normalizes Arabic only for matching, never in stored text or returned snippets. It ignores harakat and tatweel, and treats أ إ آ ٱ as ا, ى as ي, and ة as ه.

### list-posts (extended)

Adds `category_id` and `query` filters, cursor pagination (`cursor`, `next_cursor`), and per row `version`, `word_count`, `published_at` and `updated_at`.

## 8. Style guide resource

The author keeps one Markdown document of house rules in the dashboard, and every agent reads it before changing post text. This replaces repeating the same instructions in every chat.

- **Dashboard.** A "Writing style" page in settings with a Markdown editor and its own version number.
- **MCP resource.** Exposed as `blog://style-guide`, plus a `get-style-guide` tool for clients without resource support. It returns `content`, `version` and `updated_at`.
- **Read-only for agents in v2.0.** Agents can suggest rules in chat; only the author edits the document.
- **Server instructions.** The MCP server's `instructions` field says: "Before any write to post content, read the style guide and follow it. When proofreading, change only errors, never word choice."

### Seed content

The rules below were agreed while proofreading post 18 on 2026-10-04. They are a starting draft for the author to edit.

```markdown
# House style

## Language
- Posts are in Arabic (MSA narration, Omani dialect in dialogue).
- Keep dialect spellings in dialogue exactly as written, e.g. "انت", "عبد؟ نيتك تفضحينا؟".

## Proofreading scope
- Fix spelling, hamza, tanween, grammar and punctuation only.
- Never change word choice, imagery or sentence order without asking.
- Flag possible ambiguities as suggestions instead of fixing them.

## Spelling
- Indefinite accusative takes tanween on the alif: مباغتاً، أيضاً، طويلاً.
- No harakat, except where a word would be misread (e.g. كالذِّكر).
- Never vocalize deliberate double meanings (e.g. العرق: sweat / lineage).

## Punctuation
- Colon before quoted dialogue: قال: "...".
- Keep long comma-linked sentences when they hold one image or one breath.
- Use a full stop where the scene or the thought shifts.
- Straight double quotes "..." for dialogue.
```

## 9. Error catalogue

Every error uses the shape in section 2. The `details` column is what the server must return; the last column is what a well-behaved agent does next.

| `code` | When | `details` | Agent should |
| --- | --- | --- | --- |
| `not_found` | Post, slug, URL or revision does not exist | `what`, `value` | Check the id or list posts |
| `invalid_param` | Missing, extra or malformed parameter | `param`, `reason` | Fix the call |
| `version_conflict` | `if_version` differs from the current version | `current_version`, `updated_at`, `source` | Re-read the post and redo the change on the new text; never resend blindly |
| `edit_failed` | One or more edit-post edits failed; nothing saved | `failures[]`, each with `index` and its own code | Fix every listed edit, then resend the whole call |
| `edit_not_found` | An `old` string has no match | `index`, `old`, `near_matches[]` | Re-read that paragraph and quote it exactly |
| `edit_ambiguous` | An `old` string matches several times | `index`, `count`, `paragraphs[]` | Add `occurrence` or `paragraph`, or widen `old` |
| `edit_overlap` | Two edits touch the same characters | `indexes[]` | Merge them into one edit |
| `edit_spans_markup` | A match crosses inline formatting | `index`, `paragraph` | Split the edit, or use update-post |
| `slug_taken` | Slug already in use | `slug`, `post_id` | Pick another slug |
| `payload_too_large` | Request over the limits in section 10 | `limit`, `actual` | Split into several calls |
| `pending_conflict` | A pending revision could not be replayed on approval | `revision_id`, `failures[]` | Re-read and stage a new pending change |
| `forbidden` | Action not allowed through the MCP, such as approving a revision | `action` | Ask the author to do it in the dashboard |
| `rate_limited` | Too many requests | `retry_after_seconds` | Wait, then retry once |

## 10. Implementation notes

The hard parts are compare-and-set on the version, mapping text edits back into HTML, and treating Arabic text byte-for-byte. Everything else is ordinary CRUD.

### Data model

- Add `version` (integer, default 1) to the posts table.
- New table `post_revisions`:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | ULID | Primary key, sortable by time |
| `post_id` | foreign key | Indexed |
| `version` | integer | Version this revision produced; null while pending |
| `base_version` | integer | For pending revisions: the version it was made against |
| `state` | enum | applied, pending, rejected, conflict, superseded |
| `source` | enum | dashboard, mcp, api |
| `client_name` | string | From the MCP client info, e.g. "Claude" |
| `note` | string, nullable | From the tool call |
| `unguarded` | boolean | Write had no `if_version` |
| `title`, `content_html`, `excerpt`, `slug`, `status` | snapshot | Full copy, not a diff, so restore is exact |
| `edits` | JSON, nullable | Original edit-post list, kept for replay on approval |
| `created_at`, `decided_at`, `decided_by` | timestamps, user | Decision fields for pending revisions |

### Version compare-and-set

- Read the post row with a row lock (`SELECT … FOR UPDATE`), compare `version`, write content and revision, increment `version`, commit. All in one transaction.
- The dashboard must save through the same service layer, so human edits also bump the version and write revisions. Otherwise compare-and-set protects nothing.

### Applying edits to HTML

- Parse with a real HTML parser into a DOM; never use regular expressions on the HTML.
- Per top-level block, build the decoded text and a map from each text offset to its text node and offset.
- Locate every `old` in that text (section 4 rules), check overlaps, then replace inside the text nodes from the end of each block backwards so earlier offsets stay valid.
- Serialize with the same entity style the editor uses today (it writes `&quot;`), so a one-word edit does not produce a noisy diff across the whole post.

### Arabic and RTL pitfalls

- **No Unicode normalization**, in storage or in matching. NFC or NFKC can rewrite Arabic sequences, and the agent's quoted text would stop matching what it read.
- **Harakat are separate code points.** `مباغتاً` is `مباغتا` plus U+064B, so plain substring matching finds `مباغتا` inside it. This is why `whole_word` exists, and why it treats combining marks as part of the word.
- **Invisible characters** (U+200E, U+200F, U+200C, U+200D, U+0640, U+00A0) are kept in all views, and drive the `near_matches` hint in `edit_not_found`.
- **Mixed direction.** Latin words inside Arabic text (PDO, Gin, American Eagle) need no handling; never insert direction marks.
- **Diff display.** Render diffs with `dir="rtl"`, at paragraph level first, then character highlights inside changed paragraphs (a diff-match-patch style algorithm).

### Limits and logging

- Max 200 edits per edit-post call, 1 MB per request, 20 posts per get-posts call.
- Log tool name, post id, versions, client name, edit count and duration. Do not log post bodies.
- The MCP has no tool to approve or reject revisions, or to edit the style guide. Those stay in the dashboard.

## 11. Acceptance tests and rollout

v2 is done when every test below passes against a copy of post 18, the 2,000-word Arabic story from the real session.

### Acceptance tests

- [ ] edit-post with 57 edits on post 18 applies all of them, increments the version by 1, writes one revision, and returns a response under 1 KB.
- [ ] The same call with one bad edit writes nothing, leaves the version unchanged, and lists every failing edit by index.
- [ ] An `old` containing `"` matches text stored as `&quot;`.
- [ ] `old: "أيضا"` with two matches fails with `edit_ambiguous` and count 2; adding `occurrence: 2` succeeds.
- [ ] `whole_word: true` with `old: "مباغتا"` does not match inside `مباغتاً`.
- [ ] An `old` that differs from the text only by U+200F fails with a `near_matches` entry naming that character.
- [ ] `new` containing `<b>` is stored as escaped text, not markup.
- [ ] Every block's `style="text-align: justify;"` attribute survives an edit.
- [ ] A stale `if_version` returns `version_conflict` and writes nothing.
- [ ] A dashboard save between an agent's read and write makes the agent's guarded write fail.
- [ ] `dry_run: true` returns a diff, changes nothing and writes no revision.
- [ ] `apply_to: "pending"` leaves the live post unchanged and shows the diff banner in the dashboard.
- [ ] Approving a pending edit-post revision after the author edited a different paragraph applies it by replay.
- [ ] Approving after the author edited the same phrase marks the revision `conflict`.
- [ ] restore-revision brings back byte-identical HTML and adds a new revision.
- [ ] get-post with `url: "https://gheith.me/posts/q3nbwPmo"` returns post 18.
- [ ] `format: "text"` decodes entities and keeps every haraka.
- [ ] Reading the HTML and writing it back unchanged leaves the stored bytes identical.
- [ ] An empty excerpt generates one that ends on a word boundary.
- [ ] Changing a published post's slug redirects the old URL with a 301.
- [ ] search-posts for `اخطاء` finds `أخطاء`.

### Rollout order

Each step ships on its own and is useful without the next ones.

1. Version token, `if_version`, lean receipts, and a revision on every save, including dashboard saves.
2. edit-post with `dry_run`, plus list-revisions, get-revision and restore-revision.
3. get-post by slug or URL, and the `format` views.
4. Pending revisions and the dashboard review banner.
5. Writable `excerpt`, `slug` and `published_at`; reversible delete-post.
6. get-posts and search-posts.
7. The style guide page, resource and server instructions.

### Open questions
