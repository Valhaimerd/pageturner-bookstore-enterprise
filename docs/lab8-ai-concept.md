# PageTurner AI Book Discovery and Customer Support Assistant Using Ollama

## 1. Problem Statement

PageTurner customers can browse categories and search by keywords, but many users do not know the exact title, author, ISBN, or category name they need. The observed problem is that customers often describe books using natural language, such as a mood, a theme, a school topic, or a learning goal. A normal keyword search works only when the user's words match text stored in the catalog. This makes discovery harder for visitors, new customers, students, and readers who need guidance but cannot describe the book with exact catalog terms.

This problem affects visitors who are still exploring, customers who want a faster way to choose books, and admins who need to understand how users search for products. Keyword search is not enough because it cannot reliably understand intent, context, reading level, or follow-up needs. For example, a customer may ask for "something inspiring about friendship" even if no book title contains those exact words.

An AI assistant solves this better by interpreting the request, mapping it to catalog topics, retrieving real PageTurner books, and explaining why each recommendation fits. Ollama is selected because it runs locally, requires no paid cloud API, avoids external API keys, supports offline development, and fits the laboratory requirement for real AI integration without depending on OpenAI, Gemini, Hugging Face, or Google APIs.

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

- The assistant works using Ollama locally.
- Normal prompts return in under 5 seconds when Ollama is running.
- The system shows a graceful error or fallback response if Ollama is unavailable.
- `FakeAIProvider` works for tests, offline demo, and controlled fallback behavior.
- No cloud API keys are required.
- All AI calls are logged.
- All AI decisions are auditable.
- AI output is escaped in views.
- Tests pass without Ollama running by using the fake provider.

## 4. AI Provider Decision

- Main provider: Ollama.
- Default local model: `llama3.2`.
- Optional embedding model: `nomic-embed-text`, documented only unless later implemented.
- Fallback provider: `fake`.
- OpenAI, Gemini, Hugging Face, Google APIs, and paid cloud APIs are not required.

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
