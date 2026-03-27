# WS1 Phase 0: Baseline Measurement
Date: 2026-03-27
Page: canvas_page/14 (Baseline Measurement Page)
Prompt: Driesnote 01.A+01.B combined (Travel Managers, whitepaper downloads)

## Results
- Total tokens: 253,593
- API calls: 10
- Average per call: 25,359
- Estimated cost: ~$1.50-2.50 per page build (Anthropic Sonnet 4.6)

## Per-call breakdown (from ai_observability watchdog):
     1	token usage: 12189
     2	token usage: 11597
     3	token usage: 11354
     4	token usage: 38589
     5	token usage: 38471
     6	token usage: 34599
     7	token usage: 34519
     8	token usage: 34374
     9	token usage: 26422
    10	token usage: 11479

## Notes
- OpenAI key not set — no embedding calls (RAG image search failed gracefully)
- ai_observability configured: logging_enabled=true, log_input=true, log_output=true
- This is PRE-optimization baseline. WS1 target is 40-50% reduction.

