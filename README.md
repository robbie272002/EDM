# POS Admin Dashboard

A modern, responsive Point of Sale (POS) admin dashboard built with HTML, Tailwind CSS, and JavaScript.

## Project Structure

```
pos-admin/
├── index.html              # Main HTML file
├── css/
│   └── styles.css          # Custom styles
├── js/
│   ├── main.js            # Main application logic
│   ├── charts.js          # Chart configurations
│   └── config.js          # Application configuration
├── components/
│   ├── sidebar.html       # Sidebar component
│   └── header.html        # Header component
└── sections/
    ├── dashboard.html     # Dashboard section
    ├── sales.html         # Sales section
    ├── inventory.html     # Inventory section
    ├── products.html      # Products section
    ├── categories.html    # Categories section
    └── users.html         # Users section
```

## Features

- Modern and responsive design using Tailwind CSS
- Interactive charts using Chart.js
- Component-based architecture
- Real-time notifications
- Dynamic content loading
- User authentication
- Inventory management
- Sales tracking and reporting
- Product management
- Category management
- User management

## Dependencies

- [Tailwind CSS](https://tailwindcss.com/)
- [Font Awesome](https://fontawesome.com/)
- [Chart.js](https://www.chartjs.org/)

## Getting Started

1. Clone the repository
2. Open `index.html` in your browser
3. No build process required as we're using CDN links

## Development

The project uses a component-based architecture where each section is loaded dynamically. The main JavaScript file (`main.js`) handles the loading of components and section switching.

### Adding New Sections

1. Create a new HTML file in the `sections` directory
2. Add the section content with the appropriate ID
3. Add a navigation link in the sidebar
4. The section will be automatically loaded when clicked

### Styling

The project uses Tailwind CSS for styling. Custom styles can be added in `css/styles.css`.

### Charts

Chart configurations are managed in `js/charts.js`. To add a new chart:

1. Add the canvas element in the appropriate section
2. Add the chart configuration in `charts.js`
3. The chart will be automatically initialized when the section loads

## License

MIT License 