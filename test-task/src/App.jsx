import { useState, useRef } from 'react';
import './App.css';

function App() {
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [htmlContent, setHtmlContent] = useState('');
    const [loading, setLoading] = useState(false);

    const inputRef = useRef(null); // reference to input

    const handleSubmit = (e) => {
        e.preventDefault();
        const inputValue = inputRef.current.value.trim();
        if (!inputValue) return;

        setLoading(true); // disable button and show waiting text

        fetch('http://test.rs/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: inputValue })
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'error') {
                    setError(data.message);
                    setSuccess('');
                    setHtmlContent('');
                } else {
                    setSuccess('Form submitted successfully!');
                    setError('');
                    setHtmlContent(data.html);
                }
            })
            .catch(() => {
                setError('Server error');
                setSuccess('');
            })
            .finally(() => {
                setLoading(false);           // re-enable button
                inputRef.current.value = ''; // clear input
                inputRef.current.focus();    // focus input again
            });
    };

    return (
        <div className="wrapper">
            <label htmlFor="document-url">Enter document URL:</label>
            <input id="document-url" type="text" placeholder="Enter here..." ref={inputRef} />
            <button onClick={handleSubmit} disabled={loading}>
                {loading ? 'Please wait...' : 'Submit'}
            </button>

            {/* Show messages conditionally */}
            {error && <div className="message error">{error}</div>}
            {success &&
                <div className="message success">
                    {success}
                    <div dangerouslySetInnerHTML={{ __html: htmlContent }} />
                </div>
            }
        </div>
    );
}

export default App;
