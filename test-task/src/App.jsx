import { useState } from 'react'
import reactLogo from './assets/react.svg'
import './App.css'

function App() {
  const [count, setCount] = useState(0)

  return (
      <div className="wrapper">
          <label htmlFor="document-url">Enter document URL:</label>
          <input id="document-url" type="text" placeholder="Enter here..." />
          <button>Submit</button>

          <div className="message error">test</div>
          <div className="message success">test</div>
      </div>
  )
}

export default App
