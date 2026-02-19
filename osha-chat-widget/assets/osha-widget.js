/**
 * OSHA Safety Chat Widget
 * Simplified version without pre-chat form
 */

(function() {
    'use strict';

    // Configuration (passed from WordPress via wp_localize_script)
    const config = window.oshaConfig || {
        apiUrl: 'http://159.65.254.20:8080/chat',
        theme: 'light'
    };

    // DOM Elements
    let chatButton, chatPanel, chatClose, chatBack;
    let welcomeScreen, chatMessages;
    let chatForm, chatInput, chatSendBtn;
    let viewHistoryBtn, faqButtons;

    // State
    let isOpen = false;
    let isLoading = false;
    let currentView = 'welcome'; // 'welcome' or 'chat'

    // Session storage key
    const STORAGE_KEY = 'osha_chat_history';

    /**
     * Initialize widget
     */
    function init() {
        // Get DOM elements
        chatButton = document.getElementById('osha-chat-button');
        chatPanel = document.getElementById('osha-chat-panel');
        chatClose = document.getElementById('osha-chat-close');
        chatBack = document.getElementById('osha-chat-back');
        welcomeScreen = document.getElementById('osha-welcome-screen');
        chatMessages = document.getElementById('osha-chat-messages');
        chatForm = document.getElementById('osha-chat-form');
        chatInput = document.getElementById('osha-chat-input');
        chatSendBtn = document.getElementById('osha-chat-send');
        viewHistoryBtn = document.getElementById('osha-view-history');
        faqButtons = document.querySelectorAll('.osha-faq-btn');

        if (!chatButton || !chatPanel) {
            console.error('OSHA Chat Widget: Required elements not found');
            return;
        }

        // Event listeners
        chatButton.addEventListener('click', toggleChat);
        chatClose.addEventListener('click', closeChat);
        chatBack.addEventListener('click', showWelcomeScreen);
        chatForm.addEventListener('submit', handleSubmit);

        // Enter to send, Shift+Enter for new line
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });

        // View history button
        if (viewHistoryBtn) {
            viewHistoryBtn.addEventListener('click', handleViewHistory);
        }

        // FAQ buttons
        faqButtons.forEach(btn => {
            btn.addEventListener('click', handleFAQClick);
        });

        // Input focus to switch to chat view
        chatInput.addEventListener('focus', () => {
            if (currentView === 'welcome') {
                showChatView();
            }
        });

        // Check for existing history
        if (hasChatHistory()) {
            viewHistoryBtn.style.display = 'block';
        }

        console.log('OSHA Safety Chat Widget initialized');
    }

    /**
     * Toggle chat panel
     */
    function toggleChat() {
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    /**
     * Open chat panel
     */
    function openChat() {
        isOpen = true;
        chatPanel.style.display = 'block';
        chatButton.classList.add('active');

        // Animate in
        requestAnimationFrame(() => {
            chatPanel.classList.add('active');
        });

        chatInput.focus();
    }

    /**
     * Close chat panel
     */
    function closeChat() {
        isOpen = false;
        chatPanel.classList.remove('active');
        chatButton.classList.remove('active');

        setTimeout(() => {
            chatPanel.style.display = 'none';
        }, 300);
    }

    /**
     * Show welcome screen
     */
    function showWelcomeScreen() {
        currentView = 'welcome';
        welcomeScreen.classList.remove('hidden');
        chatMessages.classList.add('hidden');
        chatBack.classList.remove('active');
    }

    /**
     * Show chat view
     */
    function showChatView() {
        currentView = 'chat';
        welcomeScreen.classList.add('hidden');
        chatMessages.classList.remove('hidden');
        chatBack.classList.add('active');

        // Add welcome message if chat is empty
        if (chatMessages.children.length === 0) {
            addDateSeparator();
            addWelcomeMessage();
        }

        chatInput.focus();
        scrollToBottom();
    }

    /**
     * Handle view history button click
     */
    function handleViewHistory() {
        loadChatHistory();
        showChatView();
    }

    /**
     * Handle FAQ button click
     */
    function handleFAQClick(e) {
        const question = e.target.getAttribute('data-question');
        if (question) {
            showChatView();
            chatInput.value = question;
            chatInput.focus();
        }
    }

    /**
     * Handle form submit
     */
    async function handleSubmit(e) {
        e.preventDefault();

        const question = chatInput.value.trim();
        if (!question || isLoading) {
            return;
        }

        // Switch to chat view if on welcome screen
        if (currentView === 'welcome') {
            showChatView();
        }

        // Clear input
        chatInput.value = '';

        // Add user message to chat
        addMessage(question, 'user');

        // Show loading indicator
        showLoading();

        try {
            // Call OSHA API
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: question
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            // Remove loading indicator
            removeLoading();

            // Add bot response
            addMessage(data.answer, 'bot', data.citations || []);

            // Save to session storage
            saveChatHistory();

        } catch (error) {
            console.error('OSHA Chat Error:', error);
            removeLoading();
            addErrorMessage('Sorry, I encountered an error connecting to the OSHA database. Please try again in a moment.');
        }
    }

    /**
     * Add welcome message
     */
    function addWelcomeMessage() {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'osha-message osha-message-bot';

        // Bot avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'osha-message-avatar';
        avatarDiv.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
        </svg>`;

        // Message wrapper
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'osha-message-wrapper';

        // Message content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'osha-message-content';
        contentDiv.innerHTML = '<p>Hi! I\'m the OSHA Safety Assistant. I can help you understand workplace safety regulations. Ask me anything about fall protection, scaffolding, PPE, hazard communication, and more!</p>';

        // Timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'osha-message-time';
        timeDiv.textContent = formatTime(new Date());

        wrapperDiv.appendChild(contentDiv);
        wrapperDiv.appendChild(timeDiv);
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(wrapperDiv);

        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    /**
     * Add date separator
     */
    function addDateSeparator(date = new Date()) {
        const separatorDiv = document.createElement('div');
        separatorDiv.className = 'osha-date-separator';

        const labelDiv = document.createElement('div');
        labelDiv.className = 'osha-date-label';
        labelDiv.textContent = formatDate(date);

        separatorDiv.appendChild(labelDiv);
        chatMessages.appendChild(separatorDiv);
    }

    /**
     * Add message to chat
     */
    function addMessage(text, type, citations = []) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `osha-message osha-message-${type}`;

        if (type === 'bot') {
            // Bot avatar
            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'osha-message-avatar';
            avatarDiv.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
            </svg>`;
            messageDiv.appendChild(avatarDiv);
        }

        // Message wrapper
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'osha-message-wrapper';

        // Message content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'osha-message-content';
        contentDiv.innerHTML = parseMarkdown(text);

        // Add citations if present
        if (citations && citations.length > 0) {
            const citationsDiv = document.createElement('div');
            citationsDiv.className = 'osha-message-citations';
            citationsDiv.innerHTML = '<strong>📚 Sources:</strong><br>';

            citations.forEach(citation => {
                const link = document.createElement('a');
                link.href = citation.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = citation.title || citation.url;
                citationsDiv.appendChild(link);
                citationsDiv.appendChild(document.createElement('br'));
            });

            contentDiv.appendChild(citationsDiv);
        }

        // Timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'osha-message-time';
        timeDiv.textContent = formatTime(new Date());

        wrapperDiv.appendChild(contentDiv);
        wrapperDiv.appendChild(timeDiv);
        messageDiv.appendChild(wrapperDiv);

        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    /**
     * Add error message
     */
    function addErrorMessage(text) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'osha-error-message';
        errorDiv.textContent = text;
        chatMessages.appendChild(errorDiv);
        scrollToBottom();
    }

    /**
     * Show loading indicator
     */
    function showLoading() {
        isLoading = true;
        chatSendBtn.disabled = true;

        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'osha-loading-indicator';
        loadingDiv.id = 'osha-loading-indicator';
        loadingDiv.innerHTML = `
            <div class="osha-message osha-message-bot">
                <div class="osha-message-avatar">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                    </svg>
                </div>
                <div class="osha-message-wrapper">
                    <div class="osha-loading-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        `;

        chatMessages.appendChild(loadingDiv);
        scrollToBottom();
    }

    /**
     * Remove loading indicator
     */
    function removeLoading() {
        isLoading = false;
        chatSendBtn.disabled = false;

        const loadingDiv = document.getElementById('osha-loading-indicator');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }

    /**
     * Scroll chat to bottom
     */
    function scrollToBottom() {
        requestAnimationFrame(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    }

    /**
     * Format date for separator
     */
    function formatDate(date) {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return 'Yesterday';
        } else {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }

    /**
     * Format time for messages
     */
    function formatTime(date) {
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    /**
     * Parse markdown to HTML
     */
    function parseMarkdown(text) {
        let html = text;

        // Escape HTML to prevent XSS
        html = html.replace(/&/g, '&amp;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;');

        // Parse bold **text**
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Parse markdown links [text](url)
        html = html.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

        // Parse bullet points
        html = html.replace(/^• (.+)$/gm, '<li>$1</li>');

        // Wrap consecutive list items in <ul>
        html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');

        // Parse line breaks
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    /**
     * Save chat history to session storage
     */
    function saveChatHistory() {
        try {
            sessionStorage.setItem(STORAGE_KEY, chatMessages.innerHTML);
            viewHistoryBtn.style.display = 'block';
        } catch (e) {
            console.error('Failed to save chat history:', e);
        }
    }

    /**
     * Load chat history from session storage
     */
    function loadChatHistory() {
        try {
            const saved = sessionStorage.getItem(STORAGE_KEY);
            if (saved) {
                chatMessages.innerHTML = saved;
                scrollToBottom();
            }
        } catch (e) {
            console.error('Failed to load chat history:', e);
        }
    }

    /**
     * Check if chat history exists
     */
    function hasChatHistory() {
        try {
            return !!sessionStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return false;
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
        document.addEventListener('DOMContentLoaded', startIconInjection);
    } else {
        init();
        startIconInjection();
    }

    // ---------------------------------------------------------
    // REDIRECT ICON INJECTION
    // ---------------------------------------------------------
    function startIconInjection() {
        // Try immediately
        addRedirectIcon();
        // Retry a few times in case header loads late
        setTimeout(addRedirectIcon, 1000);
        setTimeout(addRedirectIcon, 3000);
    }

    function addRedirectIcon() {
        // We know the ID of the signup button might be dynamic, but class usually stable.
        // Found selector: #site-header .header-desktop a.sasi-btn-primary
        // Also look for just .sasi-btn-primary in header if ID changes.
        
        const header = document.getElementById('site-header');
        if (!header) return;

        // Try to find the Signup button (it contains text "Sign up")
        const buttons = header.querySelectorAll('a.sasi-btn-primary');
        let signupBtn = null;
        
        buttons.forEach(btn => {
            if (btn.innerText.toLowerCase().includes('sign up')) {
                signupBtn = btn;
            }
        });

        if (!signupBtn) return;

        // Check if already added
        if (signupBtn.parentElement.querySelector('.osha-redirect-icon')) return;

        // Create the icon link
        const iconLink = document.createElement('a');
        iconLink.href = '/knowella-osha/'; // Link to the chat page
        iconLink.className = 'osha-redirect-icon';
        iconLink.title = 'Go to OSHA Chat';
        iconLink.style.cssText = `
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            margin-right: 12px;
            color: #FF6B35;
            transition: transform 0.2s;
            cursor: pointer;
            text-decoration: none;
            background: rgba(255, 107, 53, 0.1); /* Subtle background */
            border-radius: 8px;
        `;
        
        iconLink.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
        `;

        // Hover effect
        iconLink.addEventListener('mouseenter', () => iconLink.style.transform = 'scale(1.1)');
        iconLink.addEventListener('mouseleave', () => iconLink.style.transform = 'scale(1)');

        // Insert adjacent to button
        // If the button is in a container, put it before the button
        signupBtn.parentNode.insertBefore(iconLink, signupBtn);
        
        // Adjust spacing
        iconLink.style.marginRight = '12px';
    }

})();
