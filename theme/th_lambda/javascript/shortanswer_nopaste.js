(function () {
    // Chỉ nhắm input text trong câu hỏi shortanswer
    const INPUT_SELECTOR =
      '.que.shortanswer span.answer input[type="text"], .que.shortanswer input[name$="_answer"][type="text"]';
  
    function isBlockedTarget(el) {
      // 1) Phải là input text
      if (!(el instanceof HTMLInputElement) || el.type !== 'text') return false;
      // 2) Phải match selector hoặc nằm trong .que.shortanswer
      if (el.matches(INPUT_SELECTOR)) return true;
      const q = el.closest('.que.shortanswer');
      return !!q;
    }
  
    document.addEventListener('paste', e => {
      if (isBlockedTarget(e.target)) {
        e.preventDefault();
      } else {
        console.log('PASTE allowed on', e.target);
      }
    }, true);
    document.addEventListener('drop',  e => { if (isBlockedTarget(e.target)) e.preventDefault(); });
    document.addEventListener('dragover', e => { if (isBlockedTarget(e.target)) e.preventDefault(); });
    document.addEventListener('contextmenu', e => { if (isBlockedTarget(e.target)) e.preventDefault(); });
    document.addEventListener('keydown', e => {
      if (!isBlockedTarget(e.target)) return;
      const isPasteShortcut =
        ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) ||
        (e.shiftKey && e.key === 'Insert');
      if (isPasteShortcut) e.preventDefault();
    });
  
    // Tuỳ chọn: phát hiện dán “lọt”
    const lastValues = new WeakMap();
    document.addEventListener('focusin', e => {
      if (isBlockedTarget(e.target)) lastValues.set(e.target, e.target.value || '');
    });
    document.addEventListener('input', e => {
      if (!isBlockedTarget(e.target)) return;
      const prev = lastValues.get(e.target) || '';
      const curr = e.target.value || '';
      if (curr.length - prev.length > 3) {
        e.target.value = prev;
      } else {
        lastValues.set(e.target, curr);
      }
    });
})();
  