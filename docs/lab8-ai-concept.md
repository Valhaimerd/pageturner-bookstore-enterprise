# PageTurner AI Book Discovery and Customer Support Assistant Using OpenAI With Ollama Fallback

## 1. Problem Statement

PageTurner customers can browse categories and search by keywords, but many users do not know the exact title, author, ISBN, or category name they need. The observed problem is that customers often describe books using natural language, such as a mood, a theme, a school topic, or a learning goal. A normal keyword search works only when the user's words match text stored in the catalog. This makes discovery harder for visitors, new customers, students, and readers who need guidance but cannot describe the book with exact catalog terms.

This problem affects visitors who are still exploring, customers who want a faster way to choose books, and admins who need to understand how users search for products. Keyword search is not enough because it cannot reliably understand intent, context, reading level, or follow-up needs. For example, a customer may ask for "something inspiring about friendship" even if no book title contains those exact words.

An AI assistant solves this better by interpreting the request, mapping it to catalog topics, retrieving real PageTurner books, and explaining why each recommendation fits. OpenAI is selected as the primary free-tier cloud AI provider because it gives strong conversational reasoning and structured output support for book discovery. Ollama remains configured as the required local fallback so the system still works when OpenAI is rate-limited, temporarily unavailable, or missing during demonstrations.

## 2. AI Feature Scope

The PageTurner AI assistant should:

- Understand natural-language book requests from visitors and customers.
- Recommend books by mood, theme, category, author, topic, or learning goal.
- Retrieve real active books from the PageTurner database before answering.
- Show the book title, author, price, stock, category, and book link for each recommendation.
- Answer basic bookstore support questions using safe predefined rules.
- Refuse questions outside bookstore scope.
- Clearly say when a response is AI-generated.

## 3. Success Criteria

- The assistant works using OpenAI as the primary provider when `OPENAI_API_KEY` is configured.
- Normal prompts return in under 5 seconds for typical book discovery requests.
- The system falls back from OpenAI to Ollama when OpenAI is rate-limited or unavailable.
- `FakeAIProvider` works for tests and final controlled fallback behavior.
- API keys are stored only in `.env` and are never printed in evidence output.
- All AI calls are logged.
- All AI decisions are auditable.
- AI output is escaped in views.
- Tests pass without OpenAI or Ollama running by using faked HTTP responses and the fake provider.

## 4. AI Provider Decision

- Main provider: OpenAI.
- Default OpenAI model: `gpt-4o-mini`.
- Required local fallback provider: Ollama.
- Default local model: `llama3.2`.
- Optional embedding model: `nomic-embed-text`, documented only unless later implemented.
- Final fallback provider: `fake`.
- Gemini, Hugging Face, Google APIs, and paid cloud APIs are not required for this implementation.

## 5. Out of Scope

- No automatic price changes.
- No external competitor scraping.
- No private order data for guests.
- No unsafe SQL generation.
- No payment or financial advice.
- No invented books.

## 6. Demo Script

Sample prompts for demonstrating the assistant:

- "I want something inspiring about friendship"
- "Recommend beginner Laravel books"
- "Find books under 500 pesos about programming"
- "What should I read after learning PHP?"
- "Suggest books for learning web development step by step"
