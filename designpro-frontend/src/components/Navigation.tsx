import { useState } from 'react';
import { Menu, X, ArrowRight } from 'lucide-react';

export const Navigation = () => {
  const [isOpen, setIsOpen] = useState(false);

  const navLinks = [
    'Home',
    'About Us',
    'Courses',
    'Instructors',
    'Testimonials',
    'Blog',
  ];

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-sm border-b border-white/10">
      <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16 sm:h-20">
          {/* Logo */}
          <div className="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
            <div className="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 border-white flex items-center justify-center flex-shrink-0">
              <div className="w-3 h-3 sm:w-4 sm:h-4 rounded-full bg-white" />
            </div>
            <span className="text-white font-medium text-xs sm:text-sm whitespace-nowrap">DesignPro</span>
          </div>

          {/* Desktop Navigation */}
          <div className="hidden lg:flex">
            <div className="flex items-center gap-6 px-6 py-2 bg-gray-900/50 rounded-full border border-gray-700">
              {navLinks.map((link) => (
                <a
                  key={link}
                  href="#"
                  className="text-white/80 text-sm hover:text-white transition-colors duration-200"
                >
                  {link}
                </a>
              ))}
              <button className="flex items-center gap-2 text-white/80 hover:text-white transition-colors duration-200 text-sm group">
                Contact us
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" />
              </button>
            </div>
          </div>

          {/* Mobile menu button */}
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="lg:hidden p-2 text-white/80 hover:text-white active:text-white transition-colors duration-200 touch-target"
            aria-label="Toggle menu"
            aria-expanded={isOpen}
          >
            {isOpen ? (
              <X className="w-6 h-6" />
            ) : (
              <Menu className="w-6 h-6" />
            )}
          </button>
        </div>

        {/* Mobile Navigation */}
        {isOpen && (
          <div className="lg:hidden pb-4 space-y-1 border-t border-white/10 mt-2">
            {navLinks.map((link) => (
              <a
                key={link}
                href="#"
                className="block text-white/80 text-sm hover:text-white transition-colors duration-200 py-3 px-4 rounded-lg hover:bg-white/5 active:bg-white/10"
              >
                {link}
              </a>
            ))}
            <button className="w-full flex items-center gap-2 text-white/80 hover:text-white transition-colors duration-200 text-sm py-3 px-4 rounded-lg hover:bg-white/5 active:bg-white/10">
              Contact us
              <ArrowRight className="w-4 h-4 ml-auto" />
            </button>
          </div>
        )}
      </div>
    </nav>
  );
};

