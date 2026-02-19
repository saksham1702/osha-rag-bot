<!-- Hide WordPress header/footer when this shortcode is used -->
<style>
/* Hide WordPress theme elements for fullscreen effect */
body.page header, 
body.page .site-header,
body.page nav,
body.page .navigation,
body.page footer,
body.page .site-footer,
body.page .entry-header,
body.page .entry-footer,
body.page .site-branding,
body.page .main-navigation {
    display: none !important;
}

body.page {
    margin: 0 !important;
    padding: 0 !important;
}

body.page .site-content,
body.page .content-area,
body.page main,
body.page .entry-content {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: none !important;
}

/* Fullscreen container */
.osha-fullpage-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999;
    background: white;
}
</style>

<!-- OSHA Fullscreen Chat Interface -->
<div id="osha-fullscreen-container" class="osha-fullpage-container">
    
    <!-- Top Brand Bar -->
    <div class="osha-brand-bar">
        <div class="osha-brand-content">
            <img src="<?php echo esc_url(plugins_url('assets/llogo.png', dirname(__FILE__))); ?>" alt="Knowella Logo" style="height: 32px; width: auto;">
            <div style="display: flex; flex-direction: column; line-height: 1.1;">
                <span class="osha-brand-text">Knowella</span>
                <span class="osha-brand-sub" style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">OSHA Assistant</span>
            </div>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="osha-chat-area">
        
        <!-- Messages Container (Hidden initially) -->
        <div id="osha-messages-container" class="osha-messages-container">
            <div id="osha-messages" class="osha-messages"></div>
        </div>

        <!-- Scroll to bottom arrow button -->
        <button id="osha-scroll-bottom-btn" class="osha-scroll-bottom-btn" title="Scroll to bottom" style="display: none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 13L12 18L17 13" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7 6L12 11L17 6" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <!-- Welcome/Center Container -->
        <div id="osha-center-container" class="osha-center-container">
            
            <!-- Welcome Message -->
            <div id="osha-welcome" class="osha-welcome">
                <div class="osha-logo">
                    <img src="<?php echo esc_url(plugins_url('assets/logo4.png', dirname(__FILE__))); ?>" alt="OSHA Assistant" style="height: 64px; width: auto;">
                </div>
                <h1>Where should we begin?</h1>
                <p>Ask anything about OSHA regulations, standards, and best practices.</p>
            </div>

            <!-- Input Area -->
            <div class="osha-input-area">
                <form id="osha-chat-form" class="osha-chat-form">
                    <div class="osha-input-container">
                        <textarea
                            id="osha-chat-input"
                            class="osha-chat-input"
                            placeholder="Ask about OSHA regulations, safety standards, compliance requirements..."
                            rows="1"
                            maxlength="1000"
                        ></textarea>
                        <button type="submit" id="osha-send-btn" class="osha-send-btn" disabled>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 12H19" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 5L19 12L12 19" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </form>
                
                <!-- Quick Suggestions -->
                <div class="osha-suggestions">
                    <button class="osha-suggestion-btn" data-question="What are the fall protection requirements?">Fall Protection Requirements</button>
                    <button class="osha-suggestion-btn" data-question="Explain confined space entry procedures">Confined Space Procedures</button>
                    <button class="osha-suggestion-btn" data-question="PPE requirements for construction sites">Construction PPE</button>
                    <button class="osha-suggestion-btn" data-question="How to file an OSHA complaint?">File OSHA Complaint</button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Fullscreen OSHA Chat Styles -->
<!-- CSS is loaded via osha-widget.css -->

<script>
// OSHA Widget - Isolated namespace to avoid conflicts with Knowella widget
window.OSHAWidget = (function() {
    'use strict';

    const API_URL = '<?php echo esc_js(get_option('osha_chat_api_url', 'http://159.65.254.20:8080/chat')); ?>';
    
    // DOM Elements
    const container = document.getElementById('osha-fullscreen-container');
    const messagesContainer = document.getElementById('osha-messages-container');
    const messages = document.getElementById('osha-messages');
    const centerContainer = document.getElementById('osha-center-container');
    const welcomeEl = document.getElementById('osha-welcome');
    const form = document.getElementById('osha-chat-form');
    const input = document.getElementById('osha-chat-input');
    const sendBtn = document.getElementById('osha-send-btn');
    const scrollBottomBtn = document.getElementById('osha-scroll-bottom-btn');
    const suggestions = document.querySelectorAll('.osha-suggestion-btn');

    let isLoading = false;
    let hasMessages = false;
    let conversationHistory = [];

    // Initialize
    init();

    function init() {
        setupEventListeners();
        focusInput();
    }

    function setupEventListeners() {
        // Form submission
        form.addEventListener('submit', handleSubmit);
        
        // Input changes
        input.addEventListener('input', handleInputChange);
        input.addEventListener('keydown', handleKeydown);
        
        // Suggestion buttons
        suggestions.forEach(btn => {
            btn.addEventListener('click', () => {
                const question = btn.getAttribute('data-question');
                askQuestion(question);
            });
        });

        // Auto-resize textarea
        input.addEventListener('input', autoResize);

        // Scroll detection for showing/hiding the scroll-to-bottom button
        messagesContainer.addEventListener('scroll', handleScrollVisibility);

        // Scroll to bottom button click
        scrollBottomBtn.addEventListener('click', function() {
            scrollToBottom();
        });
    }

    function handleSubmit(e) {
        e.preventDefault();
        const question = input.value.trim();
        if (question && !isLoading) {
            askQuestion(question);
        }
    }

    function handleInputChange() {
        const isDisabled = !input.value.trim() || isLoading;
        sendBtn.disabled = isDisabled;
        // Updating SVG stroke to match button state
        const strokeColor = isDisabled ? '#94a3b8' : '#ffffff';
        sendBtn.querySelectorAll('svg path').forEach(function(path) {
            path.setAttribute('stroke', strokeColor);
        });
    }

    function handleKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    }

    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    function focusInput() {
        setTimeout(() => input.focus(), 100);
    }

    async function askQuestion(question) {
        if (isLoading) return;

        // Switch to chat mode
        if (!hasMessages) {
            switchToChatMode();
        }

        // Add user message
        addMessage(question, 'user');
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;

        // Show loading
        showLoading();

        try {
            // Prepare request with conversation history (last 5 messages)
            const requestBody = {
                message: question,
                history: conversationHistory.slice(-5)
            };

            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            // Remove loading and add response
            hideLoading();
            const answer = data.answer || 'I apologize, but I couldn\'t generate a response. Please try again.';
            addMessage(answer, 'assistant', data.citations);

            // Update conversation history
            conversationHistory.push({
                role: 'user',
                content: question
            });
            conversationHistory.push({
                role: 'assistant',
                content: answer
            });

            // Keep only last 10 messages in memory
            if (conversationHistory.length > 10) {
                conversationHistory = conversationHistory.slice(-10);
            }

        } catch (error) {
            console.error('Chat error:', error);
            hideLoading();
            addMessage('I\'m experiencing technical difficulties. Please check your connection and try again.', 'assistant');
        }

        focusInput();
    }

    function switchToChatMode() {
        hasMessages = true;
        container.classList.add('chat-mode');
        messagesContainer.style.display = 'block';
        centerContainer.classList.add('input-only');
    }

    function addMessage(content, role, citations = []) {
        const messageEl = document.createElement('div');
        messageEl.className = `osha-message ${role}`;

        const avatar = document.createElement('div');
        avatar.className = 'osha-avatar';
        
        if (role === 'assistant') {
            avatar.innerHTML = `
                <img src="<?php echo esc_url(plugins_url('assets/logo4.png', dirname(__FILE__))); ?>" alt="Knowella" style="width: 100%; height: 100%; object-fit: contain;">
            `;
        } else {
            avatar.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                </svg>
            `;
        }

        const messageContent = document.createElement('div');
        messageContent.className = 'osha-message-content';
        messageContent.innerHTML = formatMessage(content);

        // Add citations if available
        if (citations && citations.length > 0) {
            const citationsEl = document.createElement('div');
            citationsEl.className = 'osha-citations';
            citationsEl.innerHTML = '<strong>Sources:</strong>';
            citations.forEach(citation => {
                const link = document.createElement('a');
                link.href = citation.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = citation.title || citation.url;
                citationsEl.appendChild(link);
            });
            messageContent.appendChild(citationsEl);
        }

        messageEl.appendChild(avatar);
        messageEl.appendChild(messageContent);
        messages.appendChild(messageEl);

        scrollToBottom();
    }

    function showLoading() {
        isLoading = true;
        sendBtn.disabled = true;

        const loadingEl = document.createElement('div');
        loadingEl.className = 'osha-message assistant';
        loadingEl.id = 'osha-loading';
        
        loadingEl.innerHTML = `
            <div class="osha-avatar">
                <img src="<?php echo esc_url(plugins_url('assets/logo4.png', dirname(__FILE__))); ?>" alt="Knowella" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="osha-message-content">
                <div class="osha-loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;

        messages.appendChild(loadingEl);
        scrollToBottom();
    }

    function hideLoading() {
        isLoading = false;
        const loadingEl = document.getElementById('osha-loading');
        if (loadingEl) {
            loadingEl.remove();
        }
    }

    function formatMessage(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/\n\n/g, '</p><p>')
            .replace(/\n/g, '<br>')
            .replace(/^/, '<p>')
            .replace(/$/, '</p>');
    }

    function scrollToBottom() {
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 50);
    }

    // Checking if user has scrolled up from the bottom
    function handleScrollVisibility() {
        const threshold = 150;
        const distanceFromBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
        if (distanceFromBottom > threshold) {
            scrollBottomBtn.style.display = 'flex';
        } else {
            scrollBottomBtn.style.display = 'none';
        }
    }

    // Public API
    return {
        ask: function(question) {
            input.value = question;
            input.dispatchEvent(new Event('input'));
            askQuestion(question);
        }
    };
})();

// Global function for suggestion buttons (uses namespace)
window.oshaWidgetAsk = function(question) {
    if (window.OSHAWidget) {
        window.OSHAWidget.ask(question);
    }
};
</script>
