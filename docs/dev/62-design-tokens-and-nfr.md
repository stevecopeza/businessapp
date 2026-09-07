# Design Tokens and Non-Functional Requirements

## Performance Requirements

- Page load: <2 seconds
- API response: <500ms (95th percentile)
- Quote PDF generation: <3 seconds
- Database queries: <100ms

## Scalability

- Support 10,000 quotes per business
- 1,000 concurrent users (across all businesses)
- 100 API requests per second

## Security Requirements

- HTTPS only (no HTTP)
- CSRF protection on all forms
- SQL injection prevention (prepared statements)
- XSS prevention (output escaping)
- Rate limiting on API endpoints

## Accessibility

- WCAG 2.1 Level AA compliance
- Keyboard navigation support
- Screen reader compatible
- Color contrast ratio 4.5:1 minimum

## Browser Support

- Chrome/Edge (last 2 versions)
- Firefox (last 2 versions)
- Safari (last 2 versions)
- Mobile browsers (iOS Safari, Chrome Android)

## Reliability

- 99.5% uptime target
- Automated backups (daily)
- Error rate <0.1% of requests
