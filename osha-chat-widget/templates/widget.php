<!-- OSHA Safety Chat Widget -->
<div id="osha-chat-widget">
    <!-- Chat Button (floating bubble) -->
    <button id="osha-chat-button" class="osha-chat-bubble" aria-label="Open OSHA Safety Chat">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
        </svg>
    </button>

    <!-- Chat Panel -->
    <div id="osha-chat-panel" class="osha-chat-panel" style="display: none;">
        <!-- Header -->
        <div class="osha-chat-header">
            <div class="osha-chat-header-content">
                <button id="osha-chat-back" class="osha-chat-back" aria-label="Back to welcome">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="osha-chat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="osha-chat-title">OSHA Safety Assistant</h3>
                    <p class="osha-chat-status">Ask about workplace safety regulations</p>
                </div>
            </div>
            <button id="osha-chat-close" class="osha-chat-close" aria-label="Close chat">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- Welcome Screen -->
        <div id="osha-welcome-screen" class="osha-welcome-screen">
            <div class="osha-welcome-content">
                <h2>Welcome to OSHA Safety Assistant</h2>
                <p>I can help you understand OSHA workplace safety regulations. Ask me anything about fall protection, confined spaces, scaffolding, hazard communication, and more!</p>
            </div>

            <div class="osha-faq-section">
                <h4>Common Questions</h4>
                <button class="osha-faq-btn" data-question="What are OSHA fall protection requirements?">Fall Protection Requirements</button>
                <button class="osha-faq-btn" data-question="What are OSHA scaffolding regulations?">Scaffolding Regulations</button>
                <button class="osha-faq-btn" data-question="What are confined space entry procedures?">Confined Space Entry</button>
                <button class="osha-faq-btn" data-question="What are OSHA PPE requirements?">PPE Requirements</button>
            </div>

            <button id="osha-view-history" class="osha-view-history" style="display: none;">
                View previous conversation
            </button>
        </div>

        <!-- Messages Container -->
        <div id="osha-chat-messages" class="osha-chat-messages hidden"></div>

        <!-- Input Area -->
        <div class="osha-chat-input-container">
            <form id="osha-chat-form">
                <div class="osha-input-card">
                    <textarea
                        id="osha-chat-input"
                        class="osha-chat-input"
                        placeholder="Ask about OSHA regulations..."
                        maxlength="500"
                        rows="2"
                    ></textarea>
                    <div class="osha-input-bottom-row">
                        <div></div>
                        <button type="submit" id="osha-chat-send" class="osha-chat-send-btn" aria-label="Send message">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </form>
            <p class="osha-chat-disclaimer">
                OSHA Safety Assistant powered by AI. Answers are generated from official OSHA regulations but should not replace professional safety advice. Always verify critical information with qualified safety professionals.
            </p>
        </div>
    </div>
</div>
