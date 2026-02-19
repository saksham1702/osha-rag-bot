/**
 * Knowella Chat Widget v4 - Enhanced UI
 * With welcome screen, FAQs, and improved message display
 */

(function() {
    'use strict';

    // Configuration (passed from WordPress via wp_localize_script)
    const config = window.knowellaConfig || {
        apiUrl: 'http://localhost:3000/chat/knowella',
        pdfApiUrl: 'http://localhost:3000/chat/knowella/pdf',
        theme: 'light',
        logoUrl: 'wordpress/knowella-chat-widget/assets/logo4.png',
        userIconUrl: 'wordpress/knowella-chat-widget/assets/icon.svg'
    };

    // DOM Elements
    let chatButton, chatPanel, chatClose, chatBack;
    let welcomeScreen, chatMessages;
    let chatForm, chatInput, chatSendBtn;
    let viewHistoryBtn, faqButtons;

    // State
    let isOpen = false;
    let isLoading = false;
    let currentView = 'welcome'; // 'welcome', 'form', or 'chat'

    // Session storage keys
    const STORAGE_KEY = 'knowella_chat_history';
    const USER_INFO_KEY = 'knowella_user_info';
    const SESSION_ID_KEY = 'knowella_session_id';

    // User info (captured from pre-chat form)
    let userInfo = null;
    let sessionId = null;

    /**
     * Initialize widget
     */
    function init() {
        // Get DOM elements
        chatButton = document.getElementById('knowella-chat-button');
        chatPanel = document.getElementById('knowella-chat-panel');
        chatClose = document.getElementById('knowella-chat-close');
        chatBack = document.getElementById('knowella-chat-back');
        welcomeScreen = document.getElementById('knowella-welcome-screen');
        chatMessages = document.getElementById('knowella-chat-messages');
        chatForm = document.getElementById('knowella-chat-form');
        chatInput = document.getElementById('knowella-chat-input');
        chatSendBtn = document.getElementById('knowella-chat-send');
        viewHistoryBtn = document.getElementById('knowella-view-history');
        faqButtons = document.querySelectorAll('.knowella-faq-btn');

        if (!chatButton || !chatPanel) {
            console.error('Knowella Chat Widget: Required elements not found');
            return;
        }

        // Load user info and session ID from storage
        loadUserInfo();
        loadSessionId();

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

        console.log('Knowella Chat Widget v4 initialized');
    }

    /**
     * Generate unique session ID
     */
    function generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Load session ID from storage or create new one
     */
    function loadSessionId() {
        sessionId = sessionStorage.getItem(SESSION_ID_KEY);
        if (!sessionId) {
            sessionId = generateSessionId();
            sessionStorage.setItem(SESSION_ID_KEY, sessionId);
        }
    }

    /**
     * Load user info from storage
     */
    function loadUserInfo() {
        try {
            const stored = sessionStorage.getItem(USER_INFO_KEY);
            if (stored) {
                userInfo = JSON.parse(stored);
            }
        } catch (error) {
            console.warn('Failed to load user info:', error);
            userInfo = null;
        }
    }

    /**
     * Save user info to storage
     */
    function saveUserInfo(name, email) {
        userInfo = { name, email };
        try {
            sessionStorage.setItem(USER_INFO_KEY, JSON.stringify(userInfo));
        } catch (error) {
            console.warn('Failed to save user info:', error);
        }
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
        chatPanel.style.display = 'flex';
        chatButton.style.display = 'none';
        isOpen = true;

        // Check if user has already provided info
        if (!userInfo) {
            // Show pre-chat form to collect name and email
            showPreChatForm();
        } else {
            // User info exists, check if there's chat history
            const hasHistory = sessionStorage.getItem(STORAGE_KEY);
            if (hasHistory) {
                // Load history and show chat view
                loadChatHistory();
                showChatView();
            } else {
                // Show welcome screen
                showWelcomeScreen();
            }
        }
    }

    /**
     * Close chat panel
     */
    function closeChat() {
        chatPanel.style.display = 'none';
        chatButton.style.display = 'flex';
        isOpen = false;
    }

    /**
     * Show welcome screen
     */
    function showWelcomeScreen() {
        currentView = 'welcome';
        welcomeScreen.classList.remove('hidden');
        chatMessages.classList.add('hidden');
        chatBack.classList.remove('active');
        chatInput.blur();

        // Remove pre-chat form if present and show input area
        const formOverlay = document.getElementById('knowella-prechat-overlay');
        if (formOverlay) formOverlay.remove();
        const inputContainer = chatPanel.querySelector('.knowella-chat-input-container');
        if (inputContainer) inputContainer.style.display = '';
    }

    /**
     * Show chat view
     */
    function showChatView() {
        currentView = 'chat';
        welcomeScreen.classList.add('hidden');
        chatMessages.classList.remove('hidden');
        chatBack.classList.add('active');

        // Show input area (may have been hidden by pre-chat form)
        const inputContainer = chatPanel.querySelector('.knowella-chat-input-container');
        if (inputContainer) inputContainer.style.display = '';

        // Add welcome message if chat is empty
        if (chatMessages.children.length === 0) {
            addDateSeparator();
            addWelcomeMessage();
        }

        chatInput.focus();
        scrollToBottom();
    }

    /**
     * Show pre-chat form to collect user info
     */
    function showPreChatForm() {
        currentView = 'form';

        // Hide other views (don't destroy them)
        welcomeScreen.classList.add('hidden');
        chatMessages.classList.add('hidden');
        chatBack.classList.remove('active');

        // Hide input area during pre-chat form
        const inputContainer = chatPanel.querySelector('.knowella-chat-input-container');
        if (inputContainer) inputContainer.style.display = 'none';

        // Remove existing form if any
        const existingForm = document.getElementById('knowella-prechat-overlay');
        if (existingForm) existingForm.remove();

        // Create form as a separate overlay div (don't touch welcomeScreen innerHTML)
        const formOverlay = document.createElement('div');
        formOverlay.id = 'knowella-prechat-overlay';
        formOverlay.className = 'knowella-prechat-overlay';
        formOverlay.innerHTML = `
            <div class="knowella-prechat-form">
                <div class="knowella-prechat-header">
                    <img src="${config.logoUrl}" alt="Knowella" class="knowella-prechat-logo">
                    <h3>Welcome to Knowella Chat!</h3>
                    <p>Please share your details to get started</p>
                </div>
                <form id="knowella-prechat-form-element">
                    <div class="knowella-form-group">
                        <label for="knowella-user-name">Name *</label>
                        <input
                            type="text"
                            id="knowella-user-name"
                            placeholder="Your full name"
                            required
                            minlength="2"
                        >
                    </div>
                    <div class="knowella-form-group">
                        <label for="knowella-user-email">Email *</label>
                        <input
                            type="email"
                            id="knowella-user-email"
                            placeholder="your.email@example.com"
                            required
                        >
                    </div>
                    <div class="knowella-form-privacy">
                        <small>We'll use this info to provide better support. Your privacy is important to us.</small>
                    </div>
                    <button type="submit" class="knowella-prechat-submit">
                        Start Chatting
                    </button>
                </form>
            </div>
        `;

        // Insert form overlay into chat panel (after header, before input)
        const chatInputContainer = chatPanel.querySelector('.knowella-chat-input-container');
        chatPanel.insertBefore(formOverlay, chatInputContainer);

        // Add form submit handler
        const form = document.getElementById('knowella-prechat-form-element');
        if (form) {
            form.addEventListener('submit', handlePreChatFormSubmit);
        }

        // Focus on name input
        const nameInput = document.getElementById('knowella-user-name');
        if (nameInput) {
            setTimeout(() => nameInput.focus(), 100);
        }
    }

    /**
     * Handle pre-chat form submission
     */
    function handlePreChatFormSubmit(e) {
        e.preventDefault();

        const nameInput = document.getElementById('knowella-user-name');
        const emailInput = document.getElementById('knowella-user-email');

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();

        // Validate
        if (!name || name.length < 2) {
            alert('Please enter your name (at least 2 characters)');
            nameInput.focus();
            return;
        }

        if (!email || !email.includes('@')) {
            alert('Please enter a valid email address');
            emailInput.focus();
            return;
        }

        // Save user info
        saveUserInfo(name, email);

        // Remove the form overlay (original welcome screen is untouched)
        const formOverlay = document.getElementById('knowella-prechat-overlay');
        if (formOverlay) formOverlay.remove();

        // Show original welcome screen
        showWelcomeScreen();

        console.log('User info saved:', { name, email, sessionId });
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
        const question = e.target.dataset.question || e.target.textContent;

        // Switch to chat view
        showChatView();

        // Set input value and submit
        chatInput.value = question;
        chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
    }

    /**
     * Handle form submission
     */
    async function handleSubmit(e) {
        e.preventDefault();

        const question = chatInput.value.trim();

        if (!question || isLoading) {
            return;
        }

        // Switch to chat view if not already
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
            // Ensure user info exists (shouldn't happen, but safety check)
            if (!userInfo || !sessionId) {
                throw new Error('User information is missing');
            }

            // Call API with user info
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    question,
                    name: userInfo.name,
                    email: userInfo.email,
                    sessionId: sessionId
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
            console.error('Knowella Chat Error:', error);
            removeLoading();
            addErrorMessage('Sorry, I encountered an error. Please try again in a moment.');
        }
    }

    /**
     * Add welcome message
     */
    function addWelcomeMessage() {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'knowella-message knowella-message-bot';

        // Bot avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'knowella-message-avatar';
        avatarDiv.innerHTML = `<img src="${config.logoUrl}" alt="Bot" style="width: 100%; height: 100%; object-fit: contain;">`;

        // Message wrapper
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'knowella-message-wrapper';

        // Message content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'knowella-message-content';
        contentDiv.innerHTML = '<p>👋 Hi! I\'m the Knowella AI assistant. Ask me anything about Knowella\'s products, services, or solutions!</p>';

        // Timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'knowella-message-time';
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
        separatorDiv.className = 'knowella-date-separator';

        const labelDiv = document.createElement('div');
        labelDiv.className = 'knowella-date-label';
        labelDiv.textContent = formatDate(date);

        separatorDiv.appendChild(labelDiv);
        chatMessages.appendChild(separatorDiv);
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

        // Parse bullet points • item
        html = html.replace(/^• (.+)$/gm, '<li>$1</li>');

        // Wrap consecutive list items in <ul>
        html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');

        // Parse line breaks (double newline = paragraph break)
        html = html.split('\n\n').map(para => {
            // Don't wrap if it's already a list or heading
            if (para.startsWith('<ul>') || para.startsWith('<strong>')) {
                return para;
            }
            return `<p>${para.replace(/\n/g, '<br>')}</p>`;
        }).join('');

        return html;
    }

    /**
     * Add message to chat
     */
    function addMessage(text, sender, citations = []) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `knowella-message knowella-message-${sender}`;

        // Avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'knowella-message-avatar';

        if (sender === 'bot') {
            avatarDiv.innerHTML = `<img src="${config.logoUrl}" alt="Bot" style="width: 100%; height: 100%; object-fit: contain;">`;
        } else {
            avatarDiv.innerHTML = `<img src="${config.userIconUrl}" alt="User" style="width: 100%; height: 100%; object-fit: contain;">`;
        }

        // Message wrapper
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'knowella-message-wrapper';

        // Message content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'knowella-message-content';

        const textDiv = document.createElement('div');
        // Parse markdown for bot messages, plain text for user messages
        if (sender === 'bot') {
            textDiv.innerHTML = parseMarkdown(text);
        } else {
            textDiv.textContent = text;
        }
        contentDiv.appendChild(textDiv);

        // Add citations if present
        if (citations && citations.length > 0) {
            const citationsDiv = document.createElement('div');
            citationsDiv.className = 'knowella-citations';

            const citationsTitle = document.createElement('div');
            citationsTitle.className = 'knowella-citations-title';
            citationsTitle.textContent = '📚 Sources:';
            citationsDiv.appendChild(citationsTitle);

            citations.forEach(citation => {
                const citationLink = document.createElement('a');
                citationLink.href = citation.url;
                citationLink.target = '_blank';
                citationLink.rel = 'noopener noreferrer';
                citationLink.className = 'knowella-citation';

                const titleSpan = document.createElement('span');
                titleSpan.className = 'knowella-citation-title';
                titleSpan.textContent = citation.title || 'Learn more';

                const urlSpan = document.createElement('span');
                urlSpan.className = 'knowella-citation-url';
                urlSpan.textContent = citation.url;

                citationLink.appendChild(titleSpan);
                citationLink.appendChild(urlSpan);
                citationsDiv.appendChild(citationLink);
            });

            contentDiv.appendChild(citationsDiv);
        }

        // Timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'knowella-message-time';
        timeDiv.textContent = formatTime(new Date());

        wrapperDiv.appendChild(contentDiv);
        wrapperDiv.appendChild(timeDiv);
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(wrapperDiv);

        chatMessages.appendChild(messageDiv);

        // Save to session storage
        saveChatHistory();

        scrollToBottom();
    }

    /**
     * Show loading indicator
     */
    function showLoading(customMessage) {
        isLoading = true;
        chatSendBtn.disabled = true;
        chatInput.disabled = true;

        const messageDiv = document.createElement('div');
        messageDiv.className = 'knowella-message knowella-message-bot';
        messageDiv.id = 'knowella-loading';

        // Bot avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'knowella-message-avatar';
        avatarDiv.innerHTML = `<img src="${config.logoUrl}" alt="Bot" style="width: 100%; height: 100%; object-fit: contain;">`;

        // Message wrapper
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'knowella-message-wrapper';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'knowella-message-content';

        if (customMessage) {
            const messageP = document.createElement('p');
            messageP.textContent = customMessage;
            messageP.style.marginBottom = '8px';
            contentDiv.appendChild(messageP);
        }

        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'knowella-loading';
        loadingIndicator.innerHTML = `
            <div class="knowella-loading-dot"></div>
            <div class="knowella-loading-dot"></div>
            <div class="knowella-loading-dot"></div>
        `;

        contentDiv.appendChild(loadingIndicator);
        wrapperDiv.appendChild(contentDiv);
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(wrapperDiv);

        chatMessages.appendChild(messageDiv);

        scrollToBottom();
    }

    /**
     * Remove loading indicator
     */
    function removeLoading() {
        isLoading = false;
        chatSendBtn.disabled = false;
        chatInput.disabled = false;
        chatInput.focus();

        const loadingDiv = document.getElementById('knowella-loading');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }

    /**
     * Add error message
     */
    function addErrorMessage(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'knowella-error';
        errorDiv.textContent = message;

        const messageDiv = document.createElement('div');
        messageDiv.className = 'knowella-message knowella-message-bot';

        // Bot avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'knowella-message-avatar';
        avatarDiv.innerHTML = `<img src="${config.logoUrl}" alt="Bot" style="width: 100%; height: 100%; object-fit: contain;">`;

        // Message wrapper
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'knowella-message-wrapper';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'knowella-message-content';
        contentDiv.appendChild(errorDiv);

        const timeDiv = document.createElement('div');
        timeDiv.className = 'knowella-message-time';
        timeDiv.textContent = formatTime(new Date());

        wrapperDiv.appendChild(contentDiv);
        wrapperDiv.appendChild(timeDiv);
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(wrapperDiv);

        chatMessages.appendChild(messageDiv);

        scrollToBottom();
    }

    /**
     * Scroll to bottom of messages
     */
    function scrollToBottom() {
        setTimeout(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }, 100);
    }

    /**
     * Save chat history to sessionStorage
     */
    function saveChatHistory() {
        try {
            const messages = Array.from(chatMessages.children)
                .filter(msg => !msg.id || msg.id !== 'knowella-loading')
                .map(msg => msg.outerHTML);

            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
        } catch (error) {
            console.warn('Failed to save chat history:', error);
        }
    }

    /**
     * Load chat history from sessionStorage
     */
    function loadChatHistory() {
        try {
            const saved = sessionStorage.getItem(STORAGE_KEY);

            if (saved) {
                const messages = JSON.parse(saved);
                chatMessages.innerHTML = '';
                messages.forEach(html => {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    chatMessages.appendChild(temp.firstChild);
                });
                scrollToBottom();
                return true;
            }
            return false;
        } catch (error) {
            console.warn('Failed to load chat history:', error);
            return false;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
