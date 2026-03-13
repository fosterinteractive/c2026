(function () {
  const params = new URLSearchParams(window.location.search);
  const aiMessage = params.get('ai_message');
  if (!aiMessage) return;

  // Wait for AiPanel to mount, then open it.
  const observer = new MutationObserver(() => {
    if (!document.querySelector('[data-testid="canvas-ai-panel"]')) return;
    observer.disconnect();
    window.dispatchEvent(new CustomEvent('canvas:open-ai-panel'));
  });
  observer.observe(document.body, { childList: true, subtree: true });
  setTimeout(() => observer.disconnect(), 15000);

  // Wait for DeepChat to finish its own initialisation before submitting.
  window.addEventListener('canvas:ai-ready', () => {
    const deepChat = document.querySelector('deep-chat');
    if (!deepChat) return;
    setTimeout(() => {
      const input = deepChat.shadowRoot?.getElementById('text-input');
      if (input) {
        input.innerText = aiMessage;
        input.focus();

        // Enable submit button.
        deepChat.disableSubmitButton(false);

        // Position the cursor at the end to allow users to edit.
        const range = document.createRange();
        const sel = window.getSelection();
        range.selectNodeContents(input);
        range.collapse(false); // false = collapse to end
        sel.removeAllRanges();
        sel.addRange(range);
      }
    }, 2000);
  }, { once: true });
}());
