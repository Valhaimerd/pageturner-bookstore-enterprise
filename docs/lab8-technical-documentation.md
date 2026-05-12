# Laboratory Activity 8 Technical Documentation

## PageTurner AI Book Discovery and Customer Support Assistant Using Ollama

## Problem Identification

PageTurner Online Bookstore Management System already supports catalog browsing, category filtering, and keyword-based search. These features are important, but they do not fully solve the customer discovery problem in a large catalog. A customer often does not begin with an exact title, ISBN, author, publisher, or category. Instead, the customer may describe an intention: a mood, a subject, a school activity, a professional skill, a learning path, or a general theme. For example, a visitor may ask for "something inspiring about friendship," "beginner Laravel books," or "books under 500 pesos about programming." These are natural requests, but the words may not exactly match a title or category stored in the database.

The problem becomes more visible as the catalog grows. Keyword search depends on text overlap. If the stored book title says "Modern Web Application Patterns" but the customer asks "What should I read after learning PHP?", the search system may not know that the request is related to Laravel, web development, APIs, or backend programming. Similarly, a user who asks for a relaxing story, a practical guide, or a step-by-step learning path is asking for interpretation, not just matching. A normal search bar cannot consistently infer intent, reading level, or the relationship between topics.

This affects several user groups. Visitors who are not yet logged in need quick guidance before they decide to buy. Customers with accounts may want recommendations based on their current learning goal or mood. Students and beginner developers may need a sequence of books rather than one exact title. Administrators also need evidence of how the AI system is used, whether it falls back correctly, and whether it stays within bookstore scope.

Laboratory Activity 8 addresses this by adding an AI assistant that acts like a local book discovery helper. The assistant accepts natural-language questions, retrieves relevant active books from the PageTurner database, sends only those candidate books to Ollama, validates the AI response, and displays grounded recommendations. The system is intentionally local-first. It does not require OpenAI, Gemini, Hugging Face, Google APIs, or any paid cloud AI service. Ollama is selected because it runs on the developer machine or local server, supports real language-model behavior, avoids cloud API keys, and matches the requirement for AI integration without ongoing external cost.

## Solution Design

The Lab 8 solution is designed around grounded generation. The assistant is not allowed to freely invent a recommendation. Instead, it must work from real PageTurner data. The basic flow is:

```text
user question
-> validate input safety
-> retrieve relevant books from the PageTurner database
-> build a grounded prompt with only those candidate books
-> call AIServiceManager
-> use Ollama as the main provider
-> fall back to FakeAIProvider when needed
-> validate JSON output
-> remove invented book IDs
-> show recommendations
-> store conversation messages
-> log usage and audit events
```

The customer-facing UI is available at `/ai-assistant`. It uses the existing application layout and PageTurner design style. The interface includes a message box, demo prompt buttons, loading state, validation error display, assistant answer, provider badge, fallback badge, and recommendation cards. Each recommendation card shows the book title, author, price, stock, category, reason, and a link to the book detail page. The page also shows the notice: "AI-generated suggestions. Please verify book details before buying."

The API backend exposes assistant functionality for programmatic use. The message endpoint is `POST /api/ai/book-assistant/message`. Conversation listing and detail endpoints are `GET /api/ai/book-assistant/conversations` and `GET /api/ai/book-assistant/conversations/{conversation}`. Authenticated users have conversations tied to `user_id`. Guest web users use the Laravel session ID. Guest API users use the `X-AI-Session-ID` header because API routes are stateless. This prevents one guest or user from reading another conversation.

The recommendation service does not send the full books table to the model. `BookDiscoveryAIService` uses the existing repository behavior to retrieve a small set of candidate books, normally five to ten records. It includes only needed fields such as ID, slug, title, author, price, stock, category, description, status, publisher, and format. This keeps prompts small, reduces privacy risk, improves response time, and makes output validation possible.

The assistant expects JSON with an answer, recommendations, confidence, optional follow-up question, and a `needs_human_help` flag. If Ollama returns invalid JSON, the service attempts a simple repair by extracting a JSON object. If that still fails, the service uses deterministic local recommendations from the retrieved candidate books. Any recommendation that references a book ID outside the candidate set is removed. Low confidence sets `needs_human_help=true`.

## Architecture Decisions

The AI architecture is separated into clear responsibilities so controllers do not call Ollama directly. This makes the implementation testable and keeps AI behavior centralized.

`OllamaProvider` is the real provider. It calls the local Ollama HTTP API, using the configured base URL and model. The default model is `llama3.2`. The provider uses the chat endpoint because the assistant needs a system prompt, user prompt, and structured context. It sends `stream=false` so the application receives one complete response. If Ollama is unavailable, returns an HTTP error, returns empty content, or returns invalid JSON when JSON is expected, the provider returns a failed `AIResponse` instead of throwing raw errors to the user.

`FakeAIProvider` is the deterministic fallback provider. It is used for automated tests, offline demonstrations, and graceful fallback. It never calls external services. For book discovery, it ranks supplied candidate books and returns predictable JSON. This makes PHPUnit reliable because the tests do not require a running Ollama server.

`AIServiceManager` is the only entry point for AI generation. It exposes direct and fallback generation methods. It applies input safety checks, selects the configured provider, handles fallback to fake, returns safe failure messages when both providers fail, records usage logs, and writes audit events. Controllers and services use the manager instead of creating provider instances directly.

`BookDiscoveryAIService` is the core domain service for recommendations. It receives the user question, optional user ID, optional conversation ID, and optional filters. It retrieves active in-stock candidates, builds the grounded prompt, calls the manager with feature `book_discovery_recommendations`, parses and validates the result, removes invented IDs, sanitizes output text, and returns structured recommendation data with actual `Book` models.

`AISafetyService` provides deterministic responsible AI safeguards. It blocks empty or over-length input, attempts to reveal secrets, `.env` values, API keys, hidden instructions, prompt override attempts, private user/order data requests, SQL injection-like input, and spammy or abusive content. It also sanitizes AI output by stripping raw HTML and replacing unsafe text patterns with a safe refusal. These checks are not model-based, so they run quickly and consistently.

`AIUsageTracker` writes operational records to `ai_usage_logs`. It stores provider, model, feature, token counts where available, latency, fallback flag, success flag, error code, cost estimate, user ID, conversation ID, and sanitized metadata. Cost is recorded as zero because Ollama and fake are local/free.

`AIAuditLogger` writes AI decision events to `ai_audit_events`. It records events such as `chat_response_generated`, `recommendation_generated`, `fallback_triggered`, `unsafe_input_blocked`, and `ai_unavailable`. It stores input and output hashes instead of raw sensitive content. It also writes a sanitized line to `storage/logs/ai-audit.log`.

`ProcessAIConversationSummary` is the queued summary job. It runs on the `ai-tasks` queue, accepts a conversation ID, loads recent messages safely, calls `AIServiceManager` with feature `summarization`, and stores a short summary in `ai_conversations.metadata.summary`. The job has three tries and a 60-second timeout. It preserves existing metadata and leaves the conversation unchanged if the provider fails.

The admin monitoring dashboard is available at `/admin/ai-monitoring`. It uses `ai_usage_logs` and `ai_audit_events` to show Lab 8 evidence: total calls, calls today, success and failure counts, fallback count, provider usage, Ollama calls, fake fallback calls, average latency, recent usage logs, recent audit events, top features, and total estimated cost.

## Implementation Details

The customer assistant route is `GET /ai-assistant`, and messages are posted to `POST /ai-assistant/messages`. The page is implemented in Blade and uses the existing app layout. It does not add Vue, React, or another heavy frontend framework. Alpine is already present in the project and is used only for small UI behavior such as loading state and demo prompt buttons.

When a message is submitted, `BookAssistantController` resolves or creates an `AIConversation`. Authenticated customers get a conversation with `user_id`. Guests get a conversation with `session_id`. The controller stores the user message in `ai_messages`, calls `BookDiscoveryAIService`, then stores the assistant message. The assistant message metadata includes recommended book IDs, fallback status, latency, human-help flag, and a safe error code. It does not store raw Ollama stack traces.

The API assistant uses the same controller logic but returns JSON. Because API requests are stateless, guest API clients use `X-AI-Session-ID`. If the header is missing, the server creates one and returns it in the response. The API conversation endpoints enforce ownership. Authenticated users can only read their own conversations. Guests can only read conversations matching their session header.

The recommendation retrieval path is intentionally conservative. The `BookRepository::recommendationCandidates()` method retrieves active books with `status = active` and `stock > 0`. It can use search behavior already present in the repository, including safe SQL fallback for SQLite tests. This preserves Lab 7 catalog behavior and avoids introducing a separate search system. The assistant uses existing book fields and does not rename `stock` to `stock_quantity` or `status` to `is_active`.

The JSON validation step is critical. The model response must contain a list of recommendations with `book_id`, `title`, `reason`, and `confidence`. The service checks that every ID belongs to the retrieved candidates. If the model invents an ID, the item is removed. If all model recommendations are invalid, the service falls back to deterministic ranking of retrieved books. This ensures that the user never sees a recommendation for a book that does not exist in PageTurner.

Queue summaries are disabled by default using `AI_QUEUE_SUMMARIES=false`. When enabled, the controller dispatches `ProcessAIConversationSummary` after storing an assistant response, but only when the conversation has at least three user messages. This avoids summarizing very short conversations. The job runs on `ai-tasks`, so the recommended worker command is:

```bash
php artisan queue:work --queue=ai-tasks
```

The admin monitoring dashboard is admin-only through the existing `auth`, `twofactor`, and `admin` route group. Guests are redirected to login, and customers receive forbidden access. The dashboard uses aggregate queries and limited recent lists for efficiency. It does not show raw prompts or raw AI output. Metadata is displayed in shortened safe form, and audit events show hashes.

## Testing Results

Lab 8 tests are designed to run without Ollama. The test environment uses `FakeAIProvider`, SQLite-compatible schema, no Redis requirement, no Scout engine requirement, no API keys, and no real external AI server. This ensures that the laboratory evidence can be generated quickly and consistently.

Recommended commands:

```bash
php artisan test --filter=LabEight
php artisan test --filter=AIServiceManager
php artisan test --filter=BookDiscoveryAIService
php artisan test
```

Testing result placeholders:

- Ollama response time:
- Fake fallback result:
- Queue result:
- Usage tracking result:
- Audit log result:
- Security test result:

The tests cover the customer page loading, guest recommendations, authenticated customer recommendations, message persistence, grounded active-book recommendations, inactive book exclusion, usage log creation, audit event creation, fallback behavior, graceful all-provider failure, prompt injection blocking, invalid input validation, conversation authorization, admin monitoring access, queued summary execution, and HTML-output sanitization.

`AIServiceManagerTest` verifies fallback and logging behavior. It confirms that Ollama disabled or unavailable cases use the fake provider, that both-provider failure returns a safe response, that prompt injection is blocked before provider calls, and that successful generation writes usage and audit records.

`BookDiscoveryAIServiceTest` verifies grounded recommendations. It confirms active books can be recommended, inactive books are not recommended, invented IDs are removed, invalid JSON falls back locally, low confidence escalates to human help, and raw HTML is stripped from AI output.

`LabEightAIAssistantTest` verifies the user-facing workflow. It checks page loading, guest and authenticated submissions, conversation ownership, message storage, usage and audit logging, fallback, validation, prompt injection, queue dispatch, direct summary job execution, and failure handling without metadata corruption.

`LabEightAIAdminMonitoringTest` verifies administrative evidence. It confirms admins can access `/admin/ai-monitoring`, customers cannot, and seeded usage/audit records appear with zero cost, provider names, features, and audit hashes.

## Cost Analysis

The Lab 8 implementation is local-first. Ollama runs on the local machine or local server. It does not charge per token and does not require a cloud account. The fake provider is deterministic local PHP code and is also free. Because of this, PageTurner stores `cost_estimate` as zero for the implemented providers.

This is important for a laboratory environment. Students and evaluators can run the feature without registering for paid APIs, creating billing accounts, or storing real API keys. The `.env.example` file contains safe local settings only. No OpenAI, Gemini, Hugging Face, Google, or paid cloud provider key is required.

The admin dashboard includes estimated cost total as evidence. It is expected to show zero for local Ollama and fake fallback calls. If a future version adds a paid provider, the provider abstraction and usage tracker could be extended to calculate and record actual cost per call. For this Lab 8 version, the cost analysis is simple: Ollama is free to run locally, FakeAIProvider is free, and estimated AI cost is zero.

## Responsible AI

Responsible AI is built into several layers. First, the assistant is grounded in database retrieval. It cannot recommend arbitrary model output directly. It retrieves candidate books first and validates that every recommendation refers to one of those candidates.

Second, safety checks run before provider calls. `AISafetyService` blocks attempts to reveal API keys, `.env` values, secrets, database credentials, hidden instructions, system prompts, private user/order data, SQL injection-like input, abusive input, and over-length prompts. These checks reduce the risk of prompt injection and inappropriate AI behavior.

Third, output is sanitized. The service strips raw HTML from answer text, recommendation reasons, and follow-up questions. Blade views use escaped output with `{{ }}` instead of raw rendering. This protects the customer UI from HTML injection.

Fourth, the system supports human escalation. Low-confidence responses set `needs_human_help=true`, and the UI can show that staff verification may be needed. The assistant also asks follow-up questions when a request is vague.

Fifth, observability is included. Usage logs track provider, feature, fallback, latency, and success. Audit logs record decision events using hashes instead of raw content. Admin users can review activity without exposing sensitive prompts or private data. These controls make the AI system auditable and suitable for laboratory evidence.

## Future Improvements

The first future improvement is embeddings with `nomic-embed-text`. This model can run locally with Ollama and can support semantic matching. Instead of relying mainly on keyword and SQL search, PageTurner could create embeddings for book titles, descriptions, authors, and categories. User questions could then be embedded and compared against book embeddings for stronger natural-language retrieval.

The second improvement is semantic vector search. A vector index would help match questions such as "what should I read after PHP" to Laravel, API development, web development, and software architecture books. This would make the retrieval stage stronger before the AI model ranks or explains recommendations.

The third improvement is voice search. Customers could speak a request instead of typing. This would improve accessibility and make mobile browsing faster. Voice input should still pass through the same safety, retrieval, validation, and logging pipeline.

The fourth improvement is review summarization. PageTurner already has reviews, so the AI assistant could summarize visible reviews for a book. This should be implemented carefully, using only public visible review data and clearly labeling the summary as AI-generated.

The fifth improvement is richer admin analytics. The current dashboard shows operational evidence. Future analytics could show common topics, common failed requests, fallback trends, low-confidence trends, conversion after recommendation, and category demand signals. These insights would help administrators improve catalog metadata, inventory planning, and customer support.

Overall, Laboratory Activity 8 adds an AI assistant that is practical, local-first, auditable, and safe by design. It improves book discovery without replacing the existing catalog, cart, checkout, admin, Lab 6, or Lab 7 features. The implementation keeps recommendations grounded in PageTurner data, uses Ollama as the main real AI provider, supports fake fallback for testing and offline use, and records evidence for monitoring and evaluation.
