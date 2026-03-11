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
    if (deepChat) {
      deepChat.disableSubmitButton(false);
      deepChat.submitUserMessage({ text: aiMessage });
    }
  }, { once: true });
}());
