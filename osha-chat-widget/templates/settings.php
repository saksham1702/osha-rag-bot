<div class="wrap">
    <h1>OSHA Safety Chat Widget Settings</h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('osha_chat_settings'); ?>
        <?php do_settings_sections('osha_chat_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="osha_chat_api_url">OSHA API Endpoint URL</label>
                </th>
                <td>
                    <input 
                        type="url" 
                        id="osha_chat_api_url" 
                        name="osha_chat_api_url" 
                        value="<?php echo esc_attr(get_option('osha_chat_api_url', 'http://159.65.254.20:8080/chat')); ?>" 
                        class="regular-text"
                        placeholder="http://159.65.254.20:8080/chat"
                    />
                    <p class="description">The URL of your OSHA RAG API endpoint (your VM server)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="osha_chat_theme">Chat Theme</label>
                </th>
                <td>
                    <select id="osha_chat_theme" name="osha_chat_theme">
                        <option value="red" <?php selected(get_option('osha_chat_theme', 'red'), 'red'); ?>>Red (OSHA Theme)</option>
                        <option value="light" <?php selected(get_option('osha_chat_theme', 'red'), 'light'); ?>>Light</option>
                        <option value="dark" <?php selected(get_option('osha_chat_theme', 'red'), 'dark'); ?>>Dark</option>
                    </select>
                    <p class="description">Color theme for the chat interface</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2>OSHA Chat Status</h2>
    <p>The OSHA chat interface is available via:</p>
    <ul>
        <li><strong>Shortcode:</strong> <code>[osha_fullpage_chat]</code> - Use in any page content</li>
        <li><strong>Custom Template:</strong> "OSHA Chat Fullscreen" - For pages without headers/footers</li>
    </ul>
    
    <h3>API Connection Test</h3>
    <p>
        <button type="button" id="test-osha-api" class="button button-primary">Test OSHA API Connection</button>
        <span id="osha-api-result" style="margin-left: 10px;"></span>
    </p>
    
    <h3>Current Configuration</h3>
    <table class="widefat">
        <tr>
            <td><strong>API Endpoint:</strong></td>
            <td><code><?php echo esc_html(get_option('osha_chat_api_url', 'http://159.65.254.20:8080/chat')); ?></code></td>
        </tr>
        <tr>
            <td><strong>Theme:</strong></td>
            <td><?php echo esc_html(get_option('osha_chat_theme', 'red')); ?></td>
        </tr>
        <tr>
            <td><strong>Status:</strong></td>
            <td><span style="color: green;">✓ Active</span></td>
        </tr>
    </table>
    
    <script>
    document.getElementById('test-osha-api').addEventListener('click', function() {
        const button = this;
        const result = document.getElementById('osha-api-result');
        const apiUrl = document.getElementById('osha_chat_api_url').value;
        
        if (!apiUrl) {
            result.textContent = '✗ Please enter an API URL first';
            result.style.color = 'red';
            return;
        }
        
        button.disabled = true;
        button.textContent = 'Testing...';
        result.textContent = 'Connecting to ' + apiUrl + '...';
        result.style.color = '#666';
        
        fetch(apiUrl, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                message: 'Test connection - what are OSHA safety requirements?' 
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            result.innerHTML = '<span style="color: green;">✓ Connection successful!</span><br><small>Response received: ' + (data.answer ? data.answer.substring(0, 100) + '...' : 'OK') + '</small>';
            button.disabled = false;
            button.textContent = 'Test OSHA API Connection';
        })
        .catch(error => {
            result.innerHTML = '<span style="color: red;">✗ Connection failed</span><br><small>' + error.message + '</small>';
            button.disabled = false;
            button.textContent = 'Test OSHA API Connection';
            console.error('OSHA API Test Error:', error);
        });
    });
    </script>
    
    <style>
    .widefat td {
        padding: 8px 10px;
    }
    #osha-api-result small {
        display: block;
        margin-top: 5px;
        color: #666;
    }
    </style>
</div>