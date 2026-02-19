<?php
/**
 * Template Name: OSHA Chat Fullscreen
 * Custom template for full-screen OSHA chat without header/footer
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OSHA Safety Assistant - Knowella</title>
    
    <!-- Preload font to prevent flash of unstyled text -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=block" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=block" rel="stylesheet">
    <style>
        /* Hiding body until font loads to prevent FOUT */
        .osha-fullscreen-body { opacity: 0; transition: opacity 0.15s ease-in; }
        .osha-fullscreen-body.fonts-loaded { opacity: 1; }
    </style>
    <script>
        // Revealing page once font is loaded
        document.fonts.ready.then(function() {
            document.body.classList.add('fonts-loaded');
        });
        // Fallback: show page after 500ms even if font fails
        setTimeout(function() {
            document.body.classList.add('fonts-loaded');
        }, 500);
    </script>
    
    <!-- Custom OSHA Chat Styles -->
    <link rel="stylesheet" href="<?php echo plugins_url('../assets/osha-fullscreen.css', __FILE__); ?>?v=1.2.0">
    
    <?php wp_head(); ?>
</head>
<body class="osha-fullscreen-body">
    
    <!-- OSHA Full Screen Chat Interface -->
    <div id="osha-fullscreen-container" class="osha-fullscreen-container">
        
        <!-- Top Brand Bar -->
        <div class="osha-brand-bar">
            <div class="osha-brand-content">
                <img src="<?php echo esc_url(plugins_url('../assets/llogo.png', __FILE__)); ?>" alt="Knowella Logo" class="osha-brand-logo">
                <div class="osha-brand-text-group">
                    <span class="osha-brand-text">Knowella</span>
                    <span class="osha-brand-sub">OSHA Assistant</span>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
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
                        <img src="<?php echo esc_url(plugins_url('../assets/logo4.png', __FILE__)); ?>" alt="OSHA Assistant">
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
                                    <path d="M5 12H19" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 5L19 12L12 19" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
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

    <!-- JavaScript -->
    <script>
        // OSHA Fullscreen Chat Implementation
        (function() {
            'use strict';

            const API_URL = '<?php echo esc_js(get_option('osha_chat_api_url', 'http://159.65.254.20:8080/chat')); ?>';
            const LOGO_URL = '<?php echo esc_url(plugins_url('../assets/logo4.png', __FILE__)); ?>';
            
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
                input.style.height = Math.min(input.scrollHeight, 200) + 'px';
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
                    const response = await fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ message: question })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const data = await response.json();
                    
                    // Remove loading and add response
                    hideLoading();
                    addMessage(data.answer || 'I apologize, but I couldn\'t generate a response. Please try again.', 'assistant', data.citations);

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
                // Hiding suggestions in chat mode to free up space
                const suggestionsEl = document.querySelector('.osha-suggestions');
                if (suggestionsEl) suggestionsEl.style.display = 'none';
                centerContainer.classList.add('input-only');
            }

            function addMessage(content, role, citations = []) {
                const messageEl = document.createElement('div');
                messageEl.className = `osha-message ${role}`;

                const avatar = document.createElement('div');
                avatar.className = 'osha-avatar';
                
                if (role === 'assistant') {
                    avatar.innerHTML = `<img src="${LOGO_URL}" alt="Knowella" style="width: 100%; height: 100%; object-fit: contain;">`;
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
                        <img src="${LOGO_URL}" alt="Knowella" style="width: 100%; height: 100%; object-fit: contain;">
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
                requestAnimationFrame(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    // Double-scroll to ensure it catches dynamic content
                    setTimeout(() => {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }, 100);
                });
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

        })();
    </script>

    <?php wp_footer(); ?>
</body>
</html>